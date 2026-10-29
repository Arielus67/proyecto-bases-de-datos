<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\ComandaNoSql;
use App\Models\TiempoCocinaNoSql;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ControllerCocina extends Controller
{
    public function index()
    {
        // Consultar comandas activas directamente desde la colección de MongoDB
        $comandas = ComandaNoSql::whereIn('estado', ['ABIERTO', 'EN_PREPARACION'])
            ->orderBy('created_at', 'asc')
            ->get();

        // Reunir los IDs de producto de todas las comandas
        $idsProductos = $comandas
            ->flatMap(fn ($c) => collect($c->detalles_con_modificadores)->pluck('id_producto'))
            ->unique()
            ->values()
            ->all();

        // Buscar los nombres en SQL Server: [id_producto => nombre_producto]
        $nombresProductos = [];

        if (!empty($idsProductos)) {
            $nombresProductos = Producto::whereIn('id_producto', $idsProductos)
                ->pluck('nombre_producto', 'id_producto')
                ->toArray();
        }

        return view('modules.cocina.index', compact('comandas', 'nombresProductos'));
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        //
    }

    public function show(string $id)
    {
        //
    }

    public function edit(string $id)
    {
        //
    }

    public function update(Request $request, string $id)
    {
        // Soportar tanto 'estado' (enviado por la vista KDS) como 'estado_cocina'
        $nuevoEstado = $request->input('estado') ?? $request->input('estado_cocina');
        $idProducto = $request->input('id_producto');

        if (!in_array($nuevoEstado, ['EN_PREPARACION', 'LISTO'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Estado no válido.'
            ], 422);
        }

        // Buscar la comanda en MongoDB (por _id o por id_pedido_sql)
        $comandaMongo = ComandaNoSql::where('_id', $id)->first()
                        ?? ComandaNoSql::where('id_pedido_sql', (int) $id)->first();

        if (!$comandaMongo) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró la comanda.'
            ], 404);
        }

        try {
            $idPedidoSql = (int) $comandaMongo->id_pedido_sql;

            // 1. Actualizar primero SQL Server (tabla PEDIDO usa id_pedido, no id)
            DB::connection('sqlsrv')
                ->table('PEDIDO')
                ->where('id_pedido', $idPedidoSql)
                ->update(['estado' => $nuevoEstado]);

            // 2. Actualizar MongoDB (estado y tiempos)
            $comandaMongo->estado = $nuevoEstado;

            $tiempos = $comandaMongo->tiempos_cocina ?? [];
            if ($nuevoEstado === 'EN_PREPARACION') {
                $tiempos['preparando_at'] = now();
            } elseif ($nuevoEstado === 'LISTO') {
                $tiempos['listo_at'] = now();
            }
            $comandaMongo->tiempos_cocina = $tiempos;
            $comandaMongo->save();

            // 3. Registrar el evento de rendimiento en 'tiempos_cocina'
            TiempoCocinaNoSql::create([
                'id_pedido_sql' => $idPedidoSql,
                'id_producto' => $idProducto ? (int) $idProducto : null,
                'tiempo_espera_minutos' => now()->diffInMinutes($comandaMongo->created_at ?? now()),
                'estado_etapa' => $nuevoEstado,
                'registrado_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Estado de cocina actualizado correctamente'
            ]);

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        //
    }
}
