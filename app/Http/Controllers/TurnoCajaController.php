<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\TurnoCaja;
use Illuminate\Http\Request;

class TurnoCajaController extends Controller
{
    // ---------- Listado ----------
    public function index()
    {
        $items = TurnoCaja::with('empleado')->orderByDesc('fecha_apertura')->paginate(15);

        return view('modules.admin.turnos.index', compact('items'));
    }

    // ---------- Formulario de apertura ----------
    public function create()
    {
        return view('modules.admin.turnos.form', ['item' => null, 'cajeros' => $this->cajeros()]);
    }

    // ---------- Abrir turno (un cajero no puede tener dos turnos abiertos) ----------
    public function store(Request $request)
    {
        $datos = $request->validate([
            'id_empleado'   => ['required', 'integer', 'exists:CAJERO,id_empleado'],
            'monto_inicial' => ['required', 'numeric', 'min:0'],
        ]);

        $abierto = TurnoCaja::where('id_empleado', $datos['id_empleado'])->whereNull('fecha_cierre')->exists();
        if ($abierto) {
            return back()->withInput()->with('error', 'Ese cajero ya tiene un turno abierto.');
        }

        (new TurnoCaja())->forceFill($datos)->save();

        return redirect()->route('admin.turnos.index')->with('success', 'Turno abierto correctamente.');
    }

    // ---------- Formulario de edición / cierre ----------
    public function edit($id)
    {
        return view('modules.admin.turnos.form', ['item' => TurnoCaja::findOrFail($id), 'cajeros' => $this->cajeros()]);
    }

    // ---------- Actualizar / cerrar turno ----------
    public function update(Request $request, $id)
    {
        $turno = TurnoCaja::findOrFail($id);

        $datos = $request->validate([
            'id_empleado'   => ['required', 'integer', 'exists:CAJERO,id_empleado'],
            'monto_inicial' => ['required', 'numeric', 'min:0'],
            'fecha_cierre'  => ['nullable', 'date'],
            'monto_final'   => ['nullable', 'numeric', 'min:0', 'required_with:fecha_cierre'],
        ]);

        $turno->forceFill([
            'id_empleado'   => $datos['id_empleado'],
            'monto_inicial' => $datos['monto_inicial'],
            'fecha_cierre'  => $datos['fecha_cierre'] ?? null,
            'monto_final'   => $datos['monto_final'] ?? null,
        ])->save();

        return redirect()->route('admin.turnos.index')->with('success', 'Turno actualizado correctamente.');
    }

    // ---------- Cajeros para el select: id_empleado => nombre completo ----------
    private function cajeros()
    {
        return Empleado::whereHas('cajero')->orderBy('apellido')->orderBy('nombre')->get()
            ->mapWithKeys(fn ($e) => [$e->id_empleado => $e->nombre . ' ' . $e->apellido]);
    }
}
