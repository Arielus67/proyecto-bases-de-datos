<?php

namespace App\Http\Controllers;

use App\Models\Estacion;
use App\Support\Sp;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Catálogo de estaciones de cocina (cocina caliente, bebidas, repostería...).
 * Las lecturas usan Eloquent (paginación); las escrituras pasan por los
 * procedimientos sp_estacion_insertar / actualizar / eliminar.
 */
class EstacionController extends Controller
{
    public function index()
    {
        $items = Estacion::orderBy('nombre_estacion')->paginate(15);

        return view('admin.estaciones.index', compact('items'));
    }

    public function create()
    {
        return view('admin.estaciones.form', ['item' => null]);
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);

        return $this->intentar(
            fn () => Sp::insertar('sp_estacion_insertar', $datos, 'id_estacion'),
            'Estación creada correctamente.',
            'admin.estaciones.index'
        );
    }

    public function edit($id)
    {
        return view('admin.estaciones.form', ['item' => Estacion::findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        $datos = ['id_estacion' => (int) $id] + $this->validar($request, $id);

        return $this->intentar(
            fn () => Sp::ejecutar('sp_estacion_actualizar', $datos),
            'Estación actualizada correctamente.',
            'admin.estaciones.index'
        );
    }

    public function destroy($id)
    {
        return $this->intentar(
            fn () => Sp::ejecutar('sp_estacion_eliminar', ['id_estacion' => (int) $id]),
            'Estación eliminada correctamente.',
            'admin.estaciones.index'
        );
    }

    private function validar(Request $request, $id = null): array
    {
        return $request->validate([
            'nombre_estacion' => ['required', 'string', 'max:50', Rule::unique(Estacion::class, 'nombre_estacion')->ignore($id, 'id_estacion')],
        ]);
    }
}
