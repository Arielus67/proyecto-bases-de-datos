<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\ComandaNoSql;
use App\Models\TiempoCocinaNoSql;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ControllerCocina extends Controller
{
    public function index()
    {
        $comandas = ComandaNoSql::whereIn('estado', ['ABIERTO', 'EN_PREPARACION'])
            ->orderBy('created_at', 'asc')
            ->get();

        $idsProductos = $comandas
            ->flatMap(fn ($c) => collect($c->detalles_con_modificadores)->pluck('id_producto'))
            ->unique()
            ->values()
            ->all();

        $nombresProductos = [];

        if (!empty($idsProductos)) {
            $nombresProductos = Producto::whereIn('id_producto', $idsProductos)
                ->pluck('nombre_producto', 'id_producto')
                ->toArray();
        }

        return view('modules.cocina.index', compact('comandas', 'nombresProductos'));
    }

    public function create() {}
    public function store(Request $request) {}
    public function show(string $id) {}
    public function edit(string $id) {}
    public function destroy(string $id) {}

    public function update(Request $request, string $id)
    {
        $nuevoEstado = $request->input('estado') ?? $request->input('estado_cocina');
        $idProducto  = $request->input('id_producto');

        // Estados desde los que se permite avanzar a cada estado nuevo
        $origenesPermitidos = [
            'EN_PREPARACION' => ['ABIERTO'],
            'LISTO'          => ['ABIERTO', 'EN_PREPARACION'],
        ];

        if (!isset($origenesPermitidos[$nuevoEstado])) {
            return response()->json(['success' => false, 'message' => 'Estado no válido.'], 422);
        }

        $comandaMongo = ComandaNoSql::where('_id', $id)->first()
                        ?? ComandaNoSql::where('id_pedido_sql', (int) $id)->first();

        if (!$comandaMongo) {
            return response()->json(['success' => false, 'message' => 'No se encontró la comanda.'], 404);
        }

        $idPedidoSql = (int) $comandaMongo->id_pedido_sql;
        $sql = DB::connection('sqlsrv');

        // Estado que se refleja en las líneas del pedido (DETALLE_PEDIDO.estado_cocina)
        $estadoLinea   = $nuevoEstado === 'LISTO' ? 'LISTO' : 'PREPARANDO';
        $lineasOrigen  = $nuevoEstado === 'LISTO' ? ['PENDIENTE', 'PREPARANDO'] : ['PENDIENTE'];

        try {
            // 1. SQL Server: pedido y líneas, en una sola transacción
            $sql->transaction(function () use ($sql, $idPedidoSql, $nuevoEstado, $origenesPermitidos, $estadoLinea, $lineasOrigen) {
                $filas = $sql->table('PEDIDO')
                    ->where('id_pedido', $idPedidoSql)
                    ->whereIn('estado', $origenesPermitidos[$nuevoEstado])
                    ->update(['estado' => $nuevoEstado]);

                if ($filas === 0) {
                    throw new \RuntimeException('Esta comanda ya cambió de estado desde otra pantalla.');
                }

                $sql->table('DETALLE_PEDIDO')
                    ->where('id_pedido', $idPedidoSql)
                    ->whereIn('estado_cocina', $lineasOrigen)
                    ->update([
                        'estado_cocina'       => $estadoLinea,
                        'fecha_actualizacion' => DB::raw('GETDATE()'),
                    ]);
            });

            // 2. MongoDB: estado y tiempos
            $comandaMongo->estado = $nuevoEstado;

            $tiempos = $comandaMongo->tiempos_cocina ?? [];
            if ($nuevoEstado === 'EN_PREPARACION') {
                $tiempos['preparando_at'] = now();
            } else {
                $tiempos['listo_at'] = now();
            }
            $comandaMongo->tiempos_cocina = $tiempos;
            $comandaMongo->save();

            // 3. Evento de rendimiento (minutos desde que se creó la comanda)
            $minutos = max(0, (int) round(($comandaMongo->created_at ?? now())->diffInMinutes(now())));

            TiempoCocinaNoSql::create([
                'id_pedido_sql'         => $idPedidoSql,
                'id_producto'           => $idProducto ? (int) $idProducto : null,
                'tiempo_espera_minutos' => $minutos,
                'estado_etapa'          => $nuevoEstado,
                'registrado_at'         => now(),
            ]);

            return response()->json(['success' => true, 'message' => 'Estado de cocina actualizado correctamente']);

        } catch (\RuntimeException $e) {
            // El pedido ya no estaba en el estado esperado: alinear MongoDB con SQL Server
            try {
                $actual = $sql->table('PEDIDO')->where('id_pedido', $idPedidoSql)->value('estado');
                if ($actual && $actual !== $comandaMongo->estado) {
                    $comandaMongo->estado = $actual;
                    $comandaMongo->save();
                }
            } catch (\Throwable $ignorado) {
            }

            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);

        } catch (\Throwable $e) {
            report($e);
            Log::error('Error al actualizar cocina', ['pedido' => $idPedidoSql, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
