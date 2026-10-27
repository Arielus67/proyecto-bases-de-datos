<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use App\Models\Producto;

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
}
