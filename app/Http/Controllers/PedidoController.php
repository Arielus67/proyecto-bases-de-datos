<?php

namespace App\Http\Controllers;

use App\Models\BitacoraEventoNoSql;
use App\Models\ComandaNoSql;
use App\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PedidoController extends Controller
{
    // ---------- Listado con filtros (estado y fecha) ----------
    public function index(Request $request)
    {
        $request->validate([
            'estado' => ['nullable', 'in:ABIERTO,EN_PREPARACION,LISTO,ENTREGADO,FACTURADO,ANULADO'],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $items = Pedido::with(['mesa', 'empleado'])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('fecha'), fn ($q) => $q->whereDate('fecha_hora', $request->fecha))
            ->orderByDesc('fecha_hora')
            ->paginate(15);

        return view('admin.pedidos.index', compact('items'));
    }

    // ---------- Detalle del pedido ----------
    public function show($id)
    {
        $item = Pedido::with(['mesa', 'empleado', 'anuladoPor', 'detalles.producto'])->findOrFail($id);

        return view('admin.pedidos.show', compact('item'));
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

        try {
            // sp_anular_pedido: devuelve insumos pendientes, cancela las líneas, marca el pedido y libera la mesa
            // auth()->id() debe ser el id_empleado de quien anula (columna anulado_por, FK a EMPLEADO)
            DB::connection('sqlsrv')->statement(
                'EXEC sp_anular_pedido @id_pedido = ?, @id_empleado = ?, @motivo = ?',
                [$pedido->id_pedido, auth()->id(), $datos['motivo_anulacion']]
            );
        } catch (\Throwable $e) {
            Log::error('Error al anular pedido', ['pedido' => $pedido->id_pedido, 'error' => $e->getMessage()]);

            return back()->with('error', 'No se pudo anular el pedido: ' . $this->mensajeSql($e));
        }

        // SQL ya quedó confirmado; la comanda deja de aparecer en cocina
        try {
            ComandaNoSql::where('id_pedido_sql', (int) $pedido->id_pedido)->update(['estado' => 'ANULADO']);

            BitacoraEventoNoSql::create([
                'evento'     => 'PEDIDO_ANULADO',
                'detalles'   => 'Pedido #' . $pedido->id_pedido . ' anulado. Motivo: ' . $datos['motivo_anulacion'],
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Pedido anulado en SQL, pero falló MongoDB', ['pedido' => $pedido->id_pedido, 'error' => $e->getMessage()]);
        }

        return redirect()->route('admin.pedidos.show', $pedido->id_pedido)->with('success', 'Pedido anulado correctamente.');
    }
}
