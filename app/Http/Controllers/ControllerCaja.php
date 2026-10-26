<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\Pedido;
use Illuminate\Http\Request;

class ControllerCaja extends Controller
{
    public function index()
    {
        $pedidos_pendientes = Pedido::with([
            'mesa',
            'empleado',
            'detalles.producto'
        ])
            ->whereIn('estado', ['LISTO', 'ENTREGADO'])
            ->get();

        $clientes = Cliente::all();
        $metodos_pago = MetodoPago::all();

        return view(
            'modules.caja.index',
            compact('pedidos_pendientes', 'clientes', 'metodos_pago')
        );
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
        //
    }

    public function destroy(string $id)
    {
        //
    }
}
