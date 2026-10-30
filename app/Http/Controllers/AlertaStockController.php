<?php

namespace App\Http\Controllers;

use App\Models\AlertaStock;

class AlertaStockController extends Controller
{
    // ---------- Listado: primero las pendientes, luego las más recientes ----------
    public function index()
    {
        $items = AlertaStock::with('insumo')
            ->orderBy('atendida')
            ->orderByDesc('fecha_hora')
            ->paginate(15);

        return view('modules.admin.alertas.index', compact('items'));
    }

    // ---------- Marcar una alerta como atendida ----------
    public function atender($id)
    {
        AlertaStock::findOrFail($id)->forceFill(['atendida' => 1])->save();

        return back()->with('success', 'Alerta marcada como atendida.');
    }
}
