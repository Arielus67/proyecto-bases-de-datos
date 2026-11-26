<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use App\Models\Producto;
use App\Models\ComandaNoSql;
use App\Models\TiempoCocinaNoSql;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ControllerCocina extends Controller
{
    public function index(Request $request)
    {
        $sinMongo = false;

        try {
            $this->conciliarComandasActivas();
            $this->recuperarComandasPerdidas();

            $comandas = ComandaNoSql::whereIn('estado', ComandaNoSql::ESTADOS_COCINA)
                ->orderBy('created_at', 'asc')
                ->get();
        } catch (\Throwable $e) {
            Log::warning('MongoDB no disponible: la cocina se muestra desde SQL Server', ['error' => $e->getMessage()]);
            $comandas = $this->comandasDesdeSqlServer();
            $sinMongo = true;
        }

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

        $numerosMesa = Mesa::whereIn('id_mesa', $comandas->pluck('id_mesa')->unique()->values()->all())
            ->pluck('numero_mesa', 'id_mesa')
            ->toArray();

        $vista = $request->ajax() ? 'cocina.comandas.partials.grid' : 'cocina.comandas.index';

        return view($vista, compact('comandas', 'nombresProductos', 'numerosMesa', 'sinMongo'));
    }

    private function comandasDesdeSqlServer()
    {
        $sql = DB::connection('sqlsrv');

        $pedidos = $sql->table('PEDIDO')
            ->whereIn('estado', ['ABIERTO', 'EN_PREPARACION'])
            ->orderBy('fecha_hora')
            ->get(['id_pedido', 'id_mesa', 'estado', 'fecha_hora']);

        $lineas = $sql->table('DETALLE_PEDIDO')
            ->whereIn('id_pedido', $pedidos->pluck('id_pedido'))
            ->whereIn('estado_cocina', ['PENDIENTE', 'PREPARANDO'])
            ->orderBy('num_linea')
            ->get()
            ->groupBy('id_pedido');

        return $pedidos->map(fn ($p) => (new ComandaNoSql)->forceFill([
            '_id' => (string) $p->id_pedido,
            'id_pedido_sql' => (int) $p->id_pedido,
            'id_mesa' => (int) $p->id_mesa,
            'estado' => $p->estado,
            'ronda_actual' => 1,
            'ronda_at' => Carbon::parse($p->fecha_hora),
            'created_at' => Carbon::parse($p->fecha_hora),
            'detalles_con_modificadores' => ($lineas[$p->id_pedido] ?? collect())->map(fn ($d) => [
                'num_linea' => (int) $d->num_linea,
                'id_producto' => (int) $d->id_producto,
                'cantidad' => (int) $d->cantidad,
                'notas' => $d->notas ?? '',
                'ronda' => 1,
            ])->values()->all(),
        ]));
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

        try {
            $comandaMongo = ComandaNoSql::where('_id', $id)->first()
                            ?? ComandaNoSql::where('id_pedido_sql', (int) $id)->first();
        } catch (\Throwable $e) {
            Log::warning('MongoDB no disponible al actualizar cocina', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'MongoDB no está disponible. Los cambios de estado se habilitan cuando vuelva la conexión.'], 503);
        }

        if (!$comandaMongo) {
            return response()->json(['success' => false, 'message' => 'No se encontró la comanda.'], 404);
        }

        $idPedidoSql = (int) $comandaMongo->id_pedido_sql;
        $sql = DB::connection('sqlsrv');

        // Comanda fantasma: su pedido no existe en SQL Server (o es otro con el mismo número)
        if (! $comandaMongo->correspondeA(ComandaNoSql::pedidoSql($idPedidoSql))) {
            $comandaMongo->archivar('El pedido no existe en SQL Server');

            return response()->json(['success' => false, 'message' => 'Esta comanda no corresponde a ningún pedido registrado y se retiró de cocina.'], 409);
        }

        // Estado que se refleja en las líneas del pedido (DETALLE_PEDIDO.estado_cocina)
        $estadoLinea   = $nuevoEstado === 'LISTO' ? 'LISTO' : 'PREPARANDO';
        $lineasOrigen  = $nuevoEstado === 'LISTO' ? ['PENDIENTE', 'PREPARANDO'] : ['PENDIENTE'];

        try {
            // 1. SQL Server: pedido y líneas, en una sola transacción
            $sql->transaction(function () use ($sql, $idPedidoSql, $nuevoEstado, $origenesPermitidos, $estadoLinea, $lineasOrigen) {
                $actual = $sql->table('PEDIDO')
                    ->where('id_pedido', $idPedidoSql)
                    ->lockForUpdate()
                    ->value('estado');

                if (! in_array($actual, $origenesPermitidos[$nuevoEstado], true)) {
                    throw new \DomainException($actual
                        ? 'Este pedido ya está en estado ' . str_replace('_', ' ', $actual) . '. La pantalla se actualizará.'
                        : 'El pedido de esta comanda no existe en SQL Server.');
                }

                $sql->table('PEDIDO')
                    ->where('id_pedido', $idPedidoSql)
                    ->update(['estado' => $nuevoEstado]);

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
                // En SQL todas las líneas pendientes pasaron a LISTO: ninguna ronda queda en cocina
                $comandaMongo->ronda_lista = (int) ($comandaMongo->ronda_actual ?? 1);
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

        } catch (\DomainException $e) {
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
     * Evita comandas fantasma: cada comanda activa en MongoDB debe tener su pedido en SQL Server
     * (mismo número y mismo token). Las que no lo tienen se archivan; si el pedido ya avanzó
     * (LISTO, FACTURADO, ANULADO...) se copia ese estado para que deje de mostrarse en cocina.
     */
    private function conciliarComandasActivas(): void
    {
        try {
            $activas = ComandaNoSql::whereIn('estado', ComandaNoSql::ESTADOS_COCINA)->get();

            if ($activas->isEmpty()) {
                return;
            }

            $pedidos = DB::connection('sqlsrv')->table('PEDIDO')
                ->whereIn('id_pedido', $activas->pluck('id_pedido_sql')->map(fn ($i) => (int) $i)->unique()->values()->all())
                ->get(['id_pedido', 'token_idempotencia', 'estado', 'fecha_hora'])
                ->keyBy(fn ($p) => (int) $p->id_pedido);

            foreach ($activas as $comanda) {
                $pedido = $pedidos->get((int) $comanda->id_pedido_sql);

                if (! $comanda->correspondeA($pedido)) {
                    Log::warning('Comanda fantasma retirada de cocina', ['comanda' => (string) $comanda->_id, 'pedido' => $comanda->id_pedido_sql]);
                    $comanda->archivar($pedido ? 'Comanda de otro pedido con el mismo número' : 'El pedido no existe en SQL Server');
                    continue;
                }

                if ($pedido->estado !== $comanda->estado && ! in_array($pedido->estado, ComandaNoSql::ESTADOS_COCINA, true)) {
                    $comanda->estado = $pedido->estado;
                    $comanda->save();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudieron conciliar las comandas con SQL Server', ['error' => $e->getMessage()]);
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
                if ($comanda && $comanda->correspondeA($p) && in_array($comanda->estado, ComandaNoSql::ESTADOS_COCINA, true)) {
                    continue;
                }

                $bloqueo = Cache::lock(ComandaNoSql::llaveBloqueo((int) $p->id_pedido), 10);

                if (! $bloqueo->get()) {
                    continue;
                }

                try {
                    $this->recuperarComanda($sql, $p);
                } finally {
                    $bloqueo->release();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudieron recuperar comandas desde SQL Server', ['error' => $e->getMessage()]);
        }
    }

    private function recuperarComanda($sql, $p): void
    {
        // Solo la comanda de ESTE pedido; una vieja con el mismo número se archiva y se crea una nueva
        $comanda = ComandaNoSql::delPedido((int) $p->id_pedido, $p);

        if ($comanda && in_array($comanda->estado, ComandaNoSql::ESTADOS_COCINA, true)) {
            return;
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
            $comanda->ronda_lista = $ronda - 1;
            $comanda->ronda_actual = $ronda;
            $comanda->ronda_at = now();
            $comanda->save();

            return;
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
}
