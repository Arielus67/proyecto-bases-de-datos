<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\ComandaNoSql;
use App\Models\TiempoCocinaNoSql;
use Illuminate\Http\Request;

class ControllerCocina extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Mantiene tu lógica original consultando la base relacional de SQL Server
        $pedidos = Pedido::with(['mesa', 'detalles.producto'])
            ->whereIn('estado', ['ABIERTO', 'PENDIENTE', 'EN_PREPARACION'])
            ->orderBy('fecha_hora')
            ->get();

        return view('modules.cocina.index', compact('pedidos'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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
        // Actualizar el estado de la cocina tanto en SQL como en MongoDB (tiempos de cocina)
        $nuevoEstado = $request->input('estado_cocina'); // EJ: 'EN_PREPARACION', 'LISTO'
        $idProducto = $request->input('id_producto'); // Opcional, si se actualiza por plato o línea

        // 1. Actualizamos de manera flexible en MongoDB la comanda principal (tiempos y estado)
        $comandaMongo = ComandaNoSql::where('id_pedido_sql', $id)->first();

        if ($comandaMongo) {
            $comandaMongo->estado = $nuevoEstado;

            $tiempos = $comandaMongo->tiempos_cocina ?? [];
            if ($nuevoEstado == 'EN_PREPARACION') {
                $tiempos['preparando_at'] = now();
            } elseif ($nuevoEstado == 'LISTO') {
                $tiempos['listo_at'] = now();
            }
            $comandaMongo->tiempos_cocina = $tiempos;
            $comandaMongo->save();
        }

        // 2. Registramos el evento de rendimiento en la colección específica 'tiempos_cocina' (Exigido en la guía)
        TiempoCocinaNoSql::create([
            'id_pedido_sql' => (int) $id,
            'id_producto' => $idProducto ? (int) $idProducto : null,
            'tiempo_espera_minutos' => now()->diffInMinutes($comandaMongo->created_at ?? now()),
            'estado_etapa' => $nuevoEstado,
            'registrado_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Estado de cocina y tiempos en MongoDB actualizados correctamente'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
