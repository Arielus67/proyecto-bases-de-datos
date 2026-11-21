<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Support\Sp;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Catálogo de categorías del menú.
 * Las lecturas usan Eloquent (paginación); las escrituras pasan por los
 * procedimientos sp_categoria_insertar / actualizar / eliminar.
 */
class CategoriaController extends Controller
{
    public function index()
    {
        $items = Categoria::orderBy('nombre_categoria')->paginate(15);

        return view('admin.categorias.index', compact('items'));
    }

    public function create()
    {
        return view('admin.categorias.form', ['item' => null]);
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);

        return $this->intentar(
            fn () => Sp::insertar('sp_categoria_insertar', $datos, 'id_categoria'),
            'Categoría creada correctamente.',
            'admin.categorias.index'
        );
    }

    public function edit($id)
    {
        return view('admin.categorias.form', ['item' => Categoria::findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        $datos = ['id_categoria' => (int) $id] + $this->validar($request, $id);

        return $this->intentar(
            fn () => Sp::ejecutar('sp_categoria_actualizar', $datos),
            'Categoría actualizada correctamente.',
            'admin.categorias.index'
        );
    }

    public function destroy($id)
    {
        return $this->intentar(
            fn () => Sp::ejecutar('sp_categoria_eliminar', ['id_categoria' => (int) $id]),
            'Categoría eliminada correctamente.',
            'admin.categorias.index'
        );
    }

    private function validar(Request $request, $id = null): array
    {
        return $request->validate([
            'nombre_categoria' => ['required', 'string', 'max:50', Rule::unique(Categoria::class, 'nombre_categoria')->ignore($id, 'id_categoria')],
            'descripcion'      => ['nullable', 'string', 'max:200'],
        ]);
    }
}
