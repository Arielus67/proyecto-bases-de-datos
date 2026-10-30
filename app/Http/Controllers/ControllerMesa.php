<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Mesa;
use App\Models\Producto;
use App\Models\ComandaNoSql;
use App\Models\BitacoraEventoNoSql;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ControllerMesa extends Controller
{
    public function index()
    {
        $mesas = Mesa::orderBy('numero_mesa')->get();

        return view('modules.mesero.index', compact('mesas'));
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
            $jsonLineas = json_encode(
                $datos['detalles'],
                JSON_THROW_ON_ERROR
            );

            // Registrar pedido en SQL Server.
            DB::connection('sqlsrv')->statement(
                'EXEC sp_registrar_pedido
                    @token_idempotencia = ?,
                    @id_mesa = ?,
                    @id_empleado = ?,
                    @observaciones = ?,
                    @json_lineas = ?',
                [
                    $datos['token_idempotencia'],
                    $datos['id_mesa'],
                    $datos['id_empleado'],
                    $datos['observaciones'] ?? null,
                    $jsonLineas,
                ]
            );

            // Recuperar el pedido mediante su token.
            $pedidoSql = DB::connection('sqlsrv')
                ->table('PEDIDO')
                ->where('token_idempotencia', $datos['token_idempotencia'])
                ->first();

            if (!$pedidoSql) {
                throw new \Exception('No se encontró el pedido registrado en SQL Server.');
            }

            $nuevoIdPedido = $pedidoSql->id_pedido;

            // Registrar comanda en MongoDB.
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

            // Registrar evento en MongoDB.
            BitacoraEventoNoSql::create([
                'evento' => 'PEDIDO_CREADO',
                'detalles' => 'Pedido #' . $nuevoIdPedido .
                    ' creado desde la mesa ' . $datos['id_mesa'],
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => '¡Pedido registrado con éxito!',
                'id_pedido' => $nuevoIdPedido,
            ]);

        } catch (\Throwable $ex) {

            Log::error('Error al registrar pedido', [
                'error' => $ex->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $ex->getMessage(),
            ], 422);
        }
    }
}