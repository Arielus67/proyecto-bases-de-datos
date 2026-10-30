<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use Illuminate\Http\Request;

class PedidoController extends Controller
{
    // ---------- Listado con filtros (estado y fecha) ----------
    public function index(Request $request)
    {
        $items = Pedido::with(['mesa', 'empleado'])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('fecha'), fn ($q) => $q->whereDate('fecha_hora', $request->fecha))
            ->orderByDesc('fecha_hora')
            ->paginate(15);

        return view('modules.admin.pedidos.index', compact('items'));
    }

    // ---------- Detalle del pedido ----------
    public function show($id)
    {
        $item = Pedido::with(['mesa', 'empleado', 'anuladoPor', 'detalles.producto'])->findOrFail($id);

        return view('modules.admin.pedidos.show', compact('item'));
    }

    // ---------- Anular pedido (no aplica a facturados ni ya anulados) ----------
    public function anular(Request $request, $id)
    {
        $pedido = Pedido::findOrFail($id);

        if (in_array($pedido->estado, ['FACTURADO', 'ANULADO'])) {
            return back()->with('error', 'Este pedido no se puede anular.');
        }

        $datos = $request->validate([
            'motivo_anulacion' => ['required', 'string', 'max:255'],
        ]);

        // auth()->id() debe ser el id_empleado de quien anula (columna anulado_por, FK a EMPLEADO)
        $pedido->forceFill([
            'estado'           => 'ANULADO',
            'anulado_por'      => auth()->id(),
            'motivo_anulacion' => $datos['motivo_anulacion'],
        ])->save();

        return redirect()->route('admin.pedidos.show', $pedido->id_pedido)->with('success', 'Pedido anulado correctamente.');
    }
}
