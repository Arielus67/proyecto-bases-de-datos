<?php

namespace App\Http\Controllers;

use App\Models\MetodoPago;
use App\Models\Pago;
use Illuminate\Http\Request;

class PagoController extends Controller
{
    // ---------- Listado de pagos (solo lectura) con filtro por método ----------
    public function index(Request $request)
    {
        $items = Pago::with(['factura', 'metodo'])
            ->when($request->filled('id_metodo_pago'), fn ($q) => $q->where('id_metodo_pago', $request->id_metodo_pago))
            ->orderByDesc('fecha_hora')
            ->paginate(15);

        $metodos = MetodoPago::orderBy('nombre_metodo')->pluck('nombre_metodo', 'id_metodo_pago');

        return view('modules.admin.pagos.index', compact('items', 'metodos'));
    }
}
