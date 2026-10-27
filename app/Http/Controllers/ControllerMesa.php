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
     * Método para registrar el pedido (SQL Server + MongoDB)
     */
   public function store(Request $request)
    {
        $token = $request->input('token_idempotencia');
        $idMesa = $request->input('id_mesa');
        $idEmpleado = $request->input('id_empleado', 1);
        $observaciones = $request->input('observaciones');
        $detalles = $request->input('detalles');

        $jsonLineas = json_encode($detalles);
        $idSalidaAuxiliar = 0; // Variable para cumplir con el 6to parámetro que el SP exige

        try {
            // 1. SQL Server: Ejecuta el SP enviándole los 6 parámetros que espera
            DB::connection('sqlsrv')->statement(
                'EXEC sp_registrar_pedido @token_idempotencia = ?, @id_mesa = ?, @id_empleado = ?, @observaciones = ?, @json_lineas = ?, @nuevo_id_pedido = ?',
                [
                    $token,
                    $idMesa,
                    $idEmpleado,
                    $observaciones,
                    $jsonLineas,
                    &$idSalidaAuxiliar
                ]
            );

            // Obtener de forma segura el ID real del pedido recién creado usando el token
            $pedidoSql = DB::connection('sqlsrv')
                ->table('PEDIDO')
                ->where('token_idempotencia', $token)
                ->first();

            $nuevoIdPedido = $pedidoSql ? $pedidoSql->id_pedido : 0;

            // 2. MongoDB: Guardar comanda flexible y bitácora
            ComandaNoSql::create([
                'id_pedido_sql' => (int) $nuevoIdPedido,
                'token_idempotencia' => $token,
                'id_mesa' => (int) $idMesa,
                'id_empleado' => (int) $idEmpleado,
                'estado' => 'ABIERTO',
                'detalles_con_modificadores' => $detalles,
                'tiempos_cocina' => [
                    'enviado_at' => now(),
                    'preparando_at' => null,
                    'listo_at' => null
                ]
            ]);

            BitacoraEventoNoSql::create([
                'evento' => 'PEDIDO_CREADO',
                'detalles' => 'Pedido #' . $nuevoIdPedido . ' creado desde la mesa ' . $idMesa,
                'created_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => '¡Pedido registrado con éxito!',
                'id_pedido' => $nuevoIdPedido
            ], 200);

        } catch (\Exception $ex) {
            return response()->json([
                'success' => false,
                'error' => $ex->getMessage()
            ], 422);
        }
    }
}
