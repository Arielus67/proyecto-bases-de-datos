<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\ComandaNoSql;
use App\Models\TiempoCocinaNoSql;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ControllerCocina extends Controller
{
    public function index()
    {
        // Si algún pedido quedó en SQL Server sin comanda en MongoDB, se reconstruye antes de mostrar
        $this->recuperarComandasPerdidas();

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

            // 3. Evento de rendimiento: minutos desde que se envió la ronda actual
            $inicio  = $comandaMongo->ronda_at ?? $comandaMongo->created_at ?? now();
            $minutos = max(0, (int) round($inicio->diffInMinutes(now())));

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

            return response()->json(['success' => false, 'message' => $this->mensajeSql($e)], 500);
        }
    }

    /**
     * SQL Server es la fuente de verdad. Si un pedido ABIERTO / EN_PREPARACION no tiene comanda
     * en MongoDB (o la tiene cerrada por una ronda que no llegó), se reconstruye desde sus líneas.
     */
    private function recuperarComandasPerdidas(): void
    {
        try {
            $sql = DB::connection('sqlsrv');

            $pedidos = $sql->table('PEDIDO')
                ->whereIn('estado', ['ABIERTO', 'EN_PREPARACION'])
                ->get(['id_pedido', 'token_idempotencia', 'id_mesa', 'id_empleado', 'estado', 'fecha_hora']);

            if ($pedidos->isEmpty()) {
                return;
            }

            $comandas = ComandaNoSql::whereIn('id_pedido_sql', $pedidos->pluck('id_pedido')->map(fn ($i) => (int) $i)->all())
                ->get()
                ->keyBy(fn ($c) => (int) $c->id_pedido_sql);

            foreach ($pedidos as $p) {
                $comanda = $comandas->get((int) $p->id_pedido);

                // Comanda al día: no hay nada que recuperar
                if ($comanda && in_array($comanda->estado, ['ABIERTO', 'EN_PREPARACION'], true)) {
                    continue;
                }

                $ronda = $comanda ? ((int) ($comanda->ronda_actual ?? 1)) + 1 : 1;

                $detalles = $sql->table('DETALLE_PEDIDO')
                    ->where('id_pedido', $p->id_pedido)
                    ->whereIn('estado_cocina', ['PENDIENTE', 'PREPARANDO'])
                    ->orderBy('num_linea')
                    ->get()
                    ->map(fn ($d) => [
                        'num_linea'   => (int) $d->num_linea,
                        'id_producto' => (int) $d->id_producto,
                        'cantidad'    => (int) $d->cantidad,
                        'notas'       => $d->notas ?? '',
                        'ronda'       => $ronda,
                    ])
                    ->all();

                // Comanda cerrada en MongoDB, pero el pedido se reabrió en SQL: se suma la ronda perdida
                if ($comanda) {
                    $comanda->detalles_con_modificadores = collect($comanda->detalles_con_modificadores ?? [])
                        ->merge($detalles)
                        ->values()
                        ->all();
                    $comanda->estado = $p->estado;
                    $comanda->ronda_actual = $ronda;
                    $comanda->ronda_at = now();
                    $comanda->save();

                    continue;
                }

                // Pedido sin comanda en MongoDB: se crea desde SQL Server
                $inicio = Carbon::parse($p->fecha_hora);

                ComandaNoSql::create([
                    'id_pedido_sql'              => (int) $p->id_pedido,
                    'token_idempotencia'         => $p->token_idempotencia,
                    'id_mesa'                    => (int) $p->id_mesa,
                    'id_empleado'                => (int) $p->id_empleado,
                    'estado'                     => $p->estado,
                    'detalles_con_modificadores' => $detalles,
                    'tiempos_cocina'             => ['enviado_at' => $inicio, 'preparando_at' => null, 'listo_at' => null],
                    'ronda_actual'               => 1,
                    'ronda_at'                   => $inicio,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudieron recuperar comandas desde SQL Server', ['error' => $e->getMessage()]);
        }
    }
}
