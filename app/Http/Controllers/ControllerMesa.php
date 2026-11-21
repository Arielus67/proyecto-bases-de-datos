<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Mesa;
use App\Models\Producto;
use App\Models\ComandaNoSql;
use App\Models\BitacoraEventoNoSql;
use Illuminate\Support\Facades\Log;

class ControllerMesa extends Controller
{
    /** Estados de pedido que siguen abiertos (la mesa puede pedir más productos). */
    private const ESTADOS_ABIERTOS = ['ABIERTO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO'];

    public function index(Request $request)
    {
        $mesas = Mesa::orderBy('numero_mesa')->get();

        $sql = DB::connection('sqlsrv');

        // Pedido abierto de cada mesa: [id_mesa => id_pedido] (para agregarle más productos)
        $pedidosActivos = $sql->table('PEDIDO')
            ->whereIn('estado', self::ESTADOS_ABIERTOS)
            ->pluck('id_pedido', 'id_mesa');

        // Pedidos que cocina ya dejó listos y esperan que el mesero los lleve
        $listos = $sql->table('PEDIDO as p')
            ->join('MESA as m', 'm.id_mesa', '=', 'p.id_mesa')
            ->where('p.estado', 'LISTO')
            ->orderBy('p.fecha_hora')
            ->select('p.id_pedido', 'm.numero_mesa', 'm.zona')
            ->get();

        $detallesListos = $listos->isEmpty() ? collect() : $sql->table('DETALLE_PEDIDO as d')
            ->join('PRODUCTO as pr', 'pr.id_producto', '=', 'd.id_producto')
            ->whereIn('d.id_pedido', $listos->pluck('id_pedido'))
            ->where('d.estado_cocina', 'LISTO')
            ->orderBy('d.id_pedido')->orderBy('d.num_linea')
            ->select('d.id_pedido', 'pr.nombre_producto', 'd.cantidad')
            ->get()
            ->groupBy('id_pedido');

        $vista = $request->ajax() ? 'mesero.mesas.partials.estado' : 'mesero.mesas.index';

        return view($vista, compact('mesas', 'pedidosActivos', 'listos', 'detallesListos'));
    }

    public function productosMesa($id)
    {
        $mesa = Mesa::where('id_mesa', $id)->firstOrFail();

        $productos = Producto::with('categoria')
            ->where('activo', 1)
            ->orderBy('nombre_producto')
            ->get();

        $categorias = $productos
            ->groupBy('id_categoria')
            ->map(fn ($grupo) => [
                'id' => (int) $grupo->first()->id_categoria,
                'nombre' => $grupo->first()->categoria->nombre_categoria ?? 'Sin categoría',
                'productos' => $grupo->values(),
            ])
            ->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return view(
            'mesero.menu.productos',
            compact('mesa', 'categorias')
        );
    }

    /**
     * Registrar pedido en SQL Server y MongoDB.
     */
    public function store(Request $request)
    {
        $datos = $request->validate([
            'token_idempotencia' => ['required', 'string', 'max:100'],
            'id_mesa' => ['required', 'integer'],
            'observaciones' => ['nullable', 'string', 'max:255'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.num_linea' => ['required', 'integer', 'min:1'],
            'detalles.*.id_producto' => ['required', 'integer'],
            'detalles.*.cantidad' => ['required', 'integer', 'min:1', 'max:999'],
            'detalles.*.notas' => ['nullable', 'string', 'max:200'],
        ]);

        $datos['id_empleado'] = (int) $request->user()->id_empleado;

        try {
            $jsonLineas = json_encode($datos['detalles'], JSON_THROW_ON_ERROR);

            $resultado = DB::connection('sqlsrv')->select(
                'SET NOCOUNT ON;
                 DECLARE @nuevo_id INT;

                 EXEC sp_registrar_pedido
                    @token_idempotencia = ?,
                    @id_mesa = ?,
                    @id_empleado = ?,
                    @observaciones = ?,
                    @json_lineas = ?,
                    @nuevo_id_pedido = @nuevo_id OUTPUT;

                 SELECT @nuevo_id AS id_pedido;',
                [
                    $datos['token_idempotencia'],
                    $datos['id_mesa'],
                    $datos['id_empleado'],
                    $datos['observaciones'] ?? null,
                    $jsonLineas,
                ]
            );

            $nuevoIdPedido = $resultado[0]->id_pedido ?? null;

            if (!$nuevoIdPedido) {
                $pedidoSql = DB::connection('sqlsrv')
                    ->table('PEDIDO')
                    ->where('token_idempotencia', $datos['token_idempotencia'])
                    ->first();

                if (!$pedidoSql) {
                    throw new \Exception('No se encontró el pedido registrado en SQL Server.');
                }

                $nuevoIdPedido = $pedidoSql->id_pedido;
            }

        } catch (\Throwable $ex) {
            if (! $this->esDuplicado($ex)) {
                Log::error('Error al registrar pedido', ['error' => $ex->getMessage()]);

                return response()->json([
                    'success' => false,
                    'error' => $this->mensajeSql($ex),
                ], 422);
            }

            $sql = DB::connection('sqlsrv');

            $mismoToken = $sql->table('PEDIDO')
                ->where('token_idempotencia', $datos['token_idempotencia'])
                ->value('id_pedido');

            if ($mismoToken) {
                return response()->json([
                    'success' => true,
                    'message' => 'El pedido ya estaba registrado.',
                    'id_pedido' => (int) $mismoToken,
                ]);
            }

            $abierto = $sql->table('PEDIDO')
                ->where('id_mesa', $datos['id_mesa'])
                ->whereIn('estado', self::ESTADOS_ABIERTOS)
                ->value('id_pedido');

            return response()->json([
                'success' => false,
                'error' => 'La mesa ya tiene un pedido abierto' . ($abierto ? ' (#' . $abierto . ')' : '') . '. Agrega los productos a ese pedido.',
                'id_pedido_abierto' => $abierto ? (int) $abierto : null,
            ], 409);
        }

        // El pedido ya quedó en SQL Server. Si MongoDB falla no se devuelve error al mesero:
        // cocina recupera la comanda desde SQL Server (ControllerCocina::recuperarComandasPerdidas).
        try {
            $this->guardarComanda((int) $nuevoIdPedido, $datos);
        } catch (\Throwable $ex) {
            Log::warning('Pedido OK en SQL, pero falló MongoDB', [
                'pedido' => $nuevoIdPedido,
                'error' => $ex->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => '¡Pedido registrado con éxito!',
            'id_pedido' => $nuevoIdPedido,
        ]);
    }

    /**
     * Agregar productos a un pedido que sigue abierto (nueva ronda en la misma mesa).
     */
    public function agregar(Request $request, string $id)
    {
        $datos = $request->validate([
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.id_producto' => ['required', 'integer'],
            'detalles.*.cantidad' => ['required', 'integer', 'min:1', 'max:999'],
            'detalles.*.notas' => ['nullable', 'string', 'max:200'],
        ]);

        $idPedido = (int) $id;

        $lineas = collect($datos['detalles'])->map(fn ($d) => [
            'id_producto' => (int) $d['id_producto'],
            'cantidad'    => (int) $d['cantidad'],
            'notas'       => $d['notas'] ?? '',
        ])->values()->all();

        try {
            // sp_agregar_items_pedido: nuevas líneas, inventario, subtotal y pedido otra vez ABIERTO
            DB::connection('sqlsrv')->statement(
                'EXEC sp_agregar_items_pedido @id_pedido = ?, @json_lineas = ?',
                [$idPedido, json_encode($lineas, JSON_THROW_ON_ERROR)]
            );
        } catch (\Throwable $ex) {
            Log::error('Error al agregar productos al pedido', ['pedido' => $idPedido, 'error' => $ex->getMessage()]);

            return response()->json(['success' => false, 'error' => $this->mensajeSql($ex)], 422);
        }

        // SQL ya quedó confirmado; si MongoDB falla, cocina lo recupera desde SQL Server
        try {
            Cache::lock(ComandaNoSql::llaveBloqueo($idPedido), 10)
                ->block(5, fn () => $this->ampliarComanda($idPedido, $lineas));
        } catch (\Throwable $ex) {
            Log::warning('Productos agregados en SQL, pero falló MongoDB', ['pedido' => $idPedido, 'error' => $ex->getMessage()]);
        }

        return response()->json(['success' => true, 'id_pedido' => $idPedido]);
    }

    /**
     * El mesero lleva el pedido a la mesa: LISTO -> ENTREGADO.
     */
    public function entregar(string $id)
    {
        $idPedido = (int) $id;
        $sql = DB::connection('sqlsrv');

        try {
            $sql->transaction(function () use ($sql, $idPedido) {
                $actual = $sql->table('PEDIDO')
                    ->where('id_pedido', $idPedido)
                    ->lockForUpdate()
                    ->value('estado');

                if ($actual !== 'LISTO') {
                    throw new \DomainException('El pedido no está listo para entregar.');
                }

                $sql->table('PEDIDO')
                    ->where('id_pedido', $idPedido)
                    ->update(['estado' => 'ENTREGADO']);

                $sql->table('DETALLE_PEDIDO')
                    ->where('id_pedido', $idPedido)
                    ->where('estado_cocina', 'LISTO')
                    ->update([
                        'estado_cocina'       => 'ENTREGADO',
                        'fecha_actualizacion' => DB::raw('GETDATE()'),
                    ]);
            });
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 409);
        } catch (\Throwable $e) {
            Log::error('Error al entregar pedido', ['pedido' => $idPedido, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'error' => $this->mensajeSql($e)], 422);
        }

        // SQL ya quedó confirmado; si MongoDB falla no se revierte
        try {
            $comanda = ComandaNoSql::where('id_pedido_sql', $idPedido)->first();

            if ($comanda) {
                $comanda->estado = 'ENTREGADO';
                $tiempos = $comanda->tiempos_cocina ?? [];
                $tiempos['entregado_at'] = now();
                $comanda->tiempos_cocina = $tiempos;
                $comanda->save();
            }

            BitacoraEventoNoSql::create([
                'evento'     => 'PEDIDO_ENTREGADO',
                'detalles'   => 'Pedido #' . $idPedido . ' entregado en mesa',
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Entrega OK en SQL, pero falló MongoDB', ['pedido' => $idPedido, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => true]);
    }

    /** Crea la comanda en MongoDB (si el pedido ya tenía comanda, no la duplica). */
    private function guardarComanda(int $idPedido, array $datos): void
    {
        Cache::lock(ComandaNoSql::llaveBloqueo($idPedido), 10)
            ->block(5, fn () => $this->crearComanda($idPedido, $datos));
    }

    private function crearComanda(int $idPedido, array $datos): void
    {
        if (ComandaNoSql::where('id_pedido_sql', $idPedido)->exists()) {
            return;
        }

        ComandaNoSql::create([
            'id_pedido_sql' => $idPedido,
            'token_idempotencia' => $datos['token_idempotencia'],
            'id_mesa' => (int) $datos['id_mesa'],
            'id_empleado' => (int) $datos['id_empleado'],
            'estado' => 'ABIERTO',
            'detalles_con_modificadores' => collect($datos['detalles'])
                ->map(fn ($d) => $d + ['ronda' => 1])
                ->all(),
            'tiempos_cocina' => [
                'enviado_at' => now(),
                'preparando_at' => null,
                'listo_at' => null,
            ],
            'ronda_actual' => 1,
            'ronda_at' => now(),
        ]);

        BitacoraEventoNoSql::create([
            'evento' => 'PEDIDO_CREADO',
            'detalles' => 'Pedido #' . $idPedido . ' creado desde la mesa ' . $datos['id_mesa'],
            'created_at' => now(),
        ]);
    }

    /** Suma una nueva ronda a la comanda: vuelve a ABIERTO y cocina solo ve las líneas nuevas. */
    private function ampliarComanda(int $idPedido, array $lineas): void
    {
        $comanda = ComandaNoSql::where('id_pedido_sql', $idPedido)->first();

        // Sin comanda en MongoDB: cocina la reconstruye desde SQL Server
        if (!$comanda) {
            return;
        }

        $ronda = ((int) ($comanda->ronda_actual ?? 1)) + 1;

        $nuevas = collect($lineas)->map(fn ($l) => $l + ['ronda' => $ronda])->all();

        $comanda->detalles_con_modificadores = collect($comanda->detalles_con_modificadores ?? [])
            ->merge($nuevas)
            ->values()
            ->all();

        $comanda->estado = 'ABIERTO';
        $comanda->ronda_actual = $ronda;
        $comanda->ronda_at = now();
        $comanda->tiempos_cocina = [
            'enviado_at' => now(),
            'preparando_at' => null,
            'listo_at' => null,
        ];
        $comanda->save();

        BitacoraEventoNoSql::create([
            'evento' => 'PEDIDO_AMPLIADO',
            'detalles' => 'Pedido #' . $idPedido . ': ronda ' . $ronda . ' enviada a cocina',
            'created_at' => now(),
        ]);
    }
}
