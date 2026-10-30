<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Mesa;
use App\Models\Producto;
use App\Models\ComandaNoSql;
use App\Models\BitacoraEventoNoSql;
use Illuminate\Support\Facades\Log;

class ControllerMesa extends Controller
{
    public function index()
    {
        $mesas = Mesa::orderBy('numero_mesa')->get();

        $sql = DB::connection('sqlsrv');

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
            ->orderBy('d.id_pedido')->orderBy('d.num_linea')
            ->select('d.id_pedido', 'pr.nombre_producto', 'd.cantidad')
            ->get()
            ->groupBy('id_pedido');

        return view('modules.mesero.index', compact('mesas', 'listos', 'detallesListos'));
    }

    public function productosMesa($id)
    {
        $mesa = Mesa::where('id_mesa', $id)->firstOrFail();

        $productos = Producto::where('activo', 1)
            ->orderBy('nombre_producto')
            ->get();

        return view(
            'modules.mesero.partials.menu-productos',
            compact('mesa', 'productos')
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
            'id_empleado' => ['required', 'integer'],
            'observaciones' => ['nullable', 'string'],
            'detalles' => ['required', 'array', 'min:1'],
        ]);

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

            $comandaExistente = ComandaNoSql::where('id_pedido_sql', (int) $nuevoIdPedido)->first();

            if (!$comandaExistente) {
                ComandaNoSql::create([
                    'id_pedido_sql' => (int) $nuevoIdPedido,
                    'token_idempotencia' => $datos['token_idempotencia'],
                    'id_mesa' => (int) $datos['id_mesa'],
                    'id_empleado' => (int) $datos['id_empleado'],
                    'estado' => 'ABIERTO',
                    'detalles_con_modificadores' => $datos['detalles'],
                    'tiempos_cocina' => [
                        'enviado_at' => now(),
                        'preparando_at' => null,
                        'listo_at' => null,
                    ],
                ]);

                BitacoraEventoNoSql::create([
                    'evento' => 'PEDIDO_CREADO',
                    'detalles' => 'Pedido #' . $nuevoIdPedido .
                        ' creado desde la mesa ' . $datos['id_mesa'],
                    'created_at' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => '¡Pedido registrado con éxito!',
                'id_pedido' => $nuevoIdPedido,
            ]);

        } catch (\Throwable $ex) {

            Log::error('Error al registrar pedido', ['error' => $ex->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => $this->mensajeSql($ex),
            ], 422);
        }
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
                $filas = $sql->table('PEDIDO')
                    ->where('id_pedido', $idPedido)
                    ->where('estado', 'LISTO')
                    ->update(['estado' => 'ENTREGADO']);

                if ($filas === 0) {
                    throw new \RuntimeException('El pedido no está listo para entregar.');
                }

                $sql->table('DETALLE_PEDIDO')
                    ->where('id_pedido', $idPedido)
                    ->where('estado_cocina', 'LISTO')
                    ->update([
                        'estado_cocina'       => 'ENTREGADO',
                        'fecha_actualizacion' => DB::raw('GETDATE()'),
                    ]);
            });
        } catch (\RuntimeException $e) {
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

    /** Extrae solo el mensaje de SQL Server (sin la consulta ni datos de conexión). */
    private function mensajeSql(\Throwable $e): string
    {
        if (preg_match('/\[SQL Server\](.*?)\s*\(Connection:/s', $e->getMessage(), $m)) {
            return trim($m[1]);
        }

        return $e->getMessage();
    }
}
