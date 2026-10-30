<?php

namespace App\Http\Controllers;

use App\Models\AuditoriaPedido;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
    // ---------- Bitácora de pedidos (solo lectura, la llena un trigger) ----------
    public function index(Request $request)
    {
        $items = AuditoriaPedido::when($request->filled('id_pedido'), fn ($q) => $q->where('id_pedido', $request->id_pedido))
            ->orderByDesc('fecha_hora')
            ->paginate(20);

        return view('modules.admin.auditoria.index', compact('items'));
    }
}
