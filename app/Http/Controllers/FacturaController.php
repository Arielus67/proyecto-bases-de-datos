<?php

namespace App\Http\Controllers;

use App\Models\BitacoraEventoNoSql;
use App\Models\Factura;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FacturaController extends Controller
{
    // ---------- Listado con filtros (estado y fecha) ----------
    public function index(Request $request)
    {
        $items = Factura::with(['cliente', 'cajero'])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('fecha'), fn ($q) => $q->whereDate('fecha_hora', $request->fecha))
            ->orderByDesc('fecha_hora')
            ->paginate(15);

        return view('modules.admin.facturas.index', compact('items'));
    }

    // ---------- Detalle de la factura con sus pagos ----------
    public function show($id)
    {
        $item = Factura::with(['cliente', 'cajero', 'pagos.metodo', 'anuladaPor'])->findOrFail($id);

        return view('modules.admin.facturas.show', compact('item'));
    }

    // ---------- Anular factura (solo si está cobrada; exige motivo y queda registrado quién y cuándo) ----------
    public function anular(Request $request, $id)
    {
        $factura = Factura::findOrFail($id);

        if ($factura->estado !== 'COBRADA') {
            return back()->with('error', 'Esta factura ya está anulada.');
        }

        $datos = $request->validate([
            'motivo_anulacion' => ['required', 'string', 'max:255'],
        ]);

        // El WHERE estado = 'COBRADA' evita anularla dos veces si dos personas lo intentan a la vez
        // auth()->id() debe ser el id_empleado de quien anula (columna anulada_por, FK a EMPLEADO)
        $filas = Factura::where('id_factura', $factura->id_factura)
            ->where('estado', 'COBRADA')
            ->update([
                'estado'           => 'ANULADA',
                'motivo_anulacion' => $datos['motivo_anulacion'],
                'anulada_por'      => auth()->id(),
                'fecha_anulacion'  => DB::raw('GETDATE()'),
            ]);

        if ($filas === 0) {
            return back()->with('error', 'Esta factura ya está anulada.');
        }

        try {
            BitacoraEventoNoSql::create([
                'evento'     => 'FACTURA_ANULADA',
                'detalles'   => 'Factura ' . $factura->numero_factura . ' anulada. Motivo: ' . $datos['motivo_anulacion'],
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Factura anulada en SQL, pero falló la bitácora en MongoDB', ['error' => $e->getMessage()]);
        }

        return redirect()->route('admin.facturas.show', $factura->id_factura)->with('success', 'Factura anulada correctamente.');
    }
}
