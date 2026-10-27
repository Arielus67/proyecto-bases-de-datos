<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ComandaNoSql;
use App\Models\BitacoraEventoNoSql;
use Illuminate\Support\Facades\Log;

class ControllerMesero extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
     {
        // Aquí puedes cargar las mesas y el menú activo desde SQL Server
        // $mesas = DB::connection('sqlsrv')->select('SELECT * FROM vw_mesas_estado');
        // return view('modules.mesero.index', compact('mesas'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Vista para crear el formulario de pedido con el token de idempotencia
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $token = $request->input('token_idempotencia');
        $idMesa = $request->input('id_mesa');
        $idEmpleado = $request->input('id_empleado');
        $observaciones = $request->input('observaciones');
        $detalles = $request->input('detalles'); // Array con productos, cantidades y notas/modificadores libres

        $jsonLineas = json_encode($detalles);
        $nuevoIdPedido = 0;

        try {
            // 1. FUENTE DE VERDAD: SQL Server (Ejecuta el Stored Procedure con bloqueos e inventario)
            DB::connection('sqlsrv')->statement(
                'EXEC sp_registrar_pedido @token_idempotencia = ?, @id_mesa = ?, @id_empleado = ?, @observaciones = ?, @json_lineas = ?, @nuevo_id_pedido = ? OUTPUT',
                [
                    $token,
                    $idMesa,
                    $idEmpleado,
                    $observaciones,
                    $jsonLineas,
                    &$nuevoIdPedido
                ]
            );

            // 2. MUNDO NoSQL (MongoDB): Registrar comanda con modificadores libres y tiempos de cocina
            try {
                ComandaNoSql::create([
                    'id_pedido_sql' => $nuevoIdPedido,
                    'token_idempotencia' => $token,
                    'id_mesa' => $idMesa,
                    'id_empleado' => $idEmpleado,
                    'estado' => 'ABIERTO',
                    'detalles_con_modificadores' => $detalles, // Modificadores variables y alérgenos
                    'tiempos_cocina' => [
                        'enviado_at' => now(),
                        'preparando_at' => null,
                        'listo_at' => null
                    ]
                ]);

                // Registrar en la bitácora de eventos NoSQL exigida en la guía
                BitacoraEventoNoSql::create([
                    'evento' => 'PEDIDO_CREADO',
                    'detalles' => 'Pedido #' . $nuevoIdPedido . ' registrado correctamente para la mesa ' . $idMesa,
                    'created_at' => now()
                ]);

            } catch (\Exception $mongoEx) {
                // Si MongoDB falla, la venta no se detiene (SQL Server manda como fuente de verdad)
                Log::warning("Aviso: No se pudo registrar la comanda en MongoDB: " . $mongoEx->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => '¡Pedido registrado con éxito!',
                'id_pedido' => $nuevoIdPedido
            ], 200);

        } catch (\Exception $sqlEx) {
            return response()->json([
                'success' => false,
                'error' => $sqlEx->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Mostrar detalle de una comanda o pedido específico
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // Actualizar el pedido si es necesario
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // Anular pedido o eliminar línea
    }
}
