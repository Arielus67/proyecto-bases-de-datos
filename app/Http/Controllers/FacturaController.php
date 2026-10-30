<?php

namespace App\Http\Controllers;

use App\Models\Factura;
use Illuminate\Http\Request;

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
        $item = Factura::with(['cliente', 'cajero', 'pagos.metodo'])->findOrFail($id);

        return view('modules.admin.facturas.show', compact('item'));
    }

    // ---------- Anular factura (solo si está cobrada) ----------
    public function anular($id)
    {
        $factura = Factura::findOrFail($id);

        if ($factura->estado !== 'COBRADA') {
            return back()->with('error', 'Esta factura ya está anulada.');
        }

        $factura->forceFill(['estado' => 'ANULADA'])->save();

        return redirect()->route('admin.facturas.show', $factura->id_factura)->with('success', 'Factura anulada correctamente.');
    }
}
