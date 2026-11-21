<?php

namespace App\Http\Controllers;

use App\Models\AuditoriaPedido;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
    // ---------- Bitácora de pedidos (solo lectura, la llena un trigger) ----------
    public function index(Request $request)
    {
        $request->validate(['id_pedido' => ['nullable', 'integer', 'min:1']]);

        $items = AuditoriaPedido::when($request->filled('id_pedido'), fn ($q) => $q->where('id_pedido', $request->id_pedido))
            ->orderByDesc('fecha_hora')
            ->paginate(20);

        return view('admin.auditoria.index', compact('items'));
    }
}
