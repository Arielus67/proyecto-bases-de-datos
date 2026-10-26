<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use App\Models\Producto;

class ControllerMesa extends Controller
{
    public function index()
    {
        $mesas = Mesa::all();
        $productos = Producto::all();

        return view('modules.mesero.index', compact('mesas', 'productos'));
    }
}