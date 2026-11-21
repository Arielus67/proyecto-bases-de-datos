<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\TurnoCaja;
use App\Support\Sp;
use Illuminate\Http\Request;

/**
 * Turnos de caja (los abre el administrador; el cajero cierra el suyo desde Caja).
 * Escrituras por sp_turno_caja_insertar / actualizar. Que un cajero no tenga dos
 * turnos abiertos lo garantiza el índice único UQ_TURNO_CAJA_Abierto; que un turno
 * cerrado no se toque, el trigger trg_turno_caja_cerrado.
 */
class TurnoCajaController extends Controller
{
    public function index()
    {
        $items = TurnoCaja::with('empleado')->orderByDesc('fecha_apertura')->paginate(15);

        return view('admin.turnos.index', compact('items'));
    }

    public function create()
    {
        return view('admin.turnos.form', ['item' => null, 'cajeros' => $this->cajeros()]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'id_empleado'   => ['required', 'integer', 'exists:CAJERO,id_empleado'],
            'monto_inicial' => ['required', 'numeric', 'decimal:0,2', 'max:99999999.99', 'min:0'],
        ]);

        return $this->intentar(
            fn () => Sp::insertar('sp_turno_caja_insertar', ['id_empleado' => (int) $datos['id_empleado'], 'monto_inicial' => $datos['monto_inicial']], 'id_turno'),
            'Turno abierto correctamente.',
            'admin.turnos.index'
        );
    }

    public function edit($id)
    {
        return view('admin.turnos.form', ['item' => TurnoCaja::findOrFail($id), 'cajeros' => $this->cajeros()]);
    }

    public function update(Request $request, $id)
    {
        $turno = TurnoCaja::findOrFail($id);

        $datos = $request->validate([
            'id_empleado'   => ['required', 'integer', 'exists:CAJERO,id_empleado'],
            'monto_inicial' => ['required', 'numeric', 'decimal:0,2', 'max:99999999.99', 'min:0'],
            'fecha_cierre'  => ['nullable', 'date', 'after_or_equal:' . $turno->fecha_apertura, 'before_or_equal:now'],
            'monto_final'   => ['nullable', 'numeric', 'decimal:0,2', 'max:99999999.99', 'min:0', 'required_with:fecha_cierre'],
        ]);

        return $this->intentar(
            fn () => Sp::ejecutar('sp_turno_caja_actualizar', [
                'id_turno'      => $turno->id_turno,
                'id_empleado'   => (int) $datos['id_empleado'],
                'monto_inicial' => $datos['monto_inicial'],
                'fecha_cierre'  => $datos['fecha_cierre'] ?? null,
                'monto_final'   => $datos['monto_final'] ?? null,
            ]),
            'Turno actualizado correctamente.',
            'admin.turnos.index'
        );
    }

    // ---------- Cajeros para el select: id_empleado => nombre completo ----------
    private function cajeros()
    {
        return Empleado::whereHas('cajero')->orderBy('apellido')->orderBy('nombre')->get()
            ->mapWithKeys(fn ($e) => [$e->id_empleado => $e->nombre . ' ' . $e->apellido]);
    }
}
