<?php

namespace App\Http\Controllers;

use App\Models\AlertaStock;
use App\Models\Factura;
use App\Models\Mesa;
use App\Models\Pedido;

class DashboardController extends Controller
{
    // ---------- Resumen general del restaurante ----------
    public function index()
    {
        $stats = [
            // Pedidos que todavía están en curso (ni facturados ni anulados)
            'pedidos_abiertos'   => Pedido::whereNotIn('estado', ['FACTURADO', 'ANULADO'])->count(),
            // Total cobrado hoy
            'ventas_hoy'         => Factura::where('estado', 'COBRADA')->whereDate('fecha_hora', today())->sum('total'),
            'mesas_ocupadas'     => Mesa::where('estado', 'OCUPADA')->count(),
            'alertas_pendientes' => AlertaStock::where('atendida', 0)->count(),
        ];

        $ultimosPedidos = Pedido::with(['mesa', 'empleado'])
            ->orderByDesc('fecha_hora')
            ->limit(8)
            ->get();

        return view('modules.admin.dashboard', compact('stats', 'ultimosPedidos'));
    }
}
