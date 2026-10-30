<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoriaController extends Controller
{
    // ---------- Listado ----------
    public function index()
    {
        $items = Categoria::orderBy('nombre_categoria')->paginate(15);

        return view('modules.admin.categorias.index', compact('items'));
    }

    // ---------- Formulario de creación ----------
    public function create()
    {
        return view('modules.admin.categorias.form', ['item' => null]);
    }

    // ---------- Guardar nueva ----------
    public function store(Request $request)
    {
        (new Categoria())->forceFill($this->validar($request))->save();

        return redirect()->route('admin.categorias.index')->with('success', 'Categoría creada correctamente.');
    }

    // ---------- Formulario de edición ----------
    public function edit($id)
    {
        return view('modules.admin.categorias.form', ['item' => Categoria::findOrFail($id)]);
    }

    // ---------- Actualizar ----------
    public function update(Request $request, $id)
    {
        Categoria::findOrFail($id)->forceFill($this->validar($request, $id))->save();

        return redirect()->route('admin.categorias.index')->with('success', 'Categoría actualizada correctamente.');
    }

    // ---------- Eliminar ----------
    public function destroy($id)
    {
        try {
            Categoria::findOrFail($id)->delete();
        } catch (QueryException $e) {
            return back()->with('error', 'No se puede eliminar: la categoría tiene productos asociados.');
        }

        return redirect()->route('admin.categorias.index')->with('success', 'Categoría eliminada correctamente.');
    }

    // ---------- Validación ----------
    private function validar(Request $request, $id = null): array
    {
        return $request->validate([
            'nombre_categoria' => ['required', 'string', 'max:50', Rule::unique(Categoria::class, 'nombre_categoria')->ignore($id, 'id_categoria')],
            'descripcion'      => ['nullable', 'string', 'max:200'],
        ]);
    }
}
