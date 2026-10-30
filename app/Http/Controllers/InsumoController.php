<?php

namespace App\Http\Controllers;

use App\Models\Insumo;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InsumoController extends Controller
{
    // ---------- Listado ----------
    public function index()
    {
        $items = Insumo::orderBy('nombre_insumo')->paginate(15);

        return view('modules.admin.insumos.index', compact('items'));
    }

    // ---------- Formulario de creación ----------
    public function create()
    {
        return view('modules.admin.insumos.form', ['item' => null]);
    }

    // ---------- Guardar nuevo ----------
    public function store(Request $request)
    {
        (new Insumo())->forceFill($this->validar($request))->save();

        return redirect()->route('admin.insumos.index')->with('success', 'Insumo creado correctamente.');
    }

    // ---------- Formulario de edición ----------
    public function edit($id)
    {
        return view('modules.admin.insumos.form', ['item' => Insumo::findOrFail($id)]);
    }

    // ---------- Actualizar ----------
    public function update(Request $request, $id)
    {
        Insumo::findOrFail($id)->forceFill($this->validar($request, $id))->save();

        return redirect()->route('admin.insumos.index')->with('success', 'Insumo actualizado correctamente.');
    }

    // ---------- Eliminar ----------
    public function destroy($id)
    {
        try {
            Insumo::findOrFail($id)->delete();
        } catch (QueryException $e) {
            return back()->with('error', 'No se puede eliminar: el insumo se usa en recetas o tiene alertas de stock.');
        }

        return redirect()->route('admin.insumos.index')->with('success', 'Insumo eliminado correctamente.');
    }

    // ---------- Validación ----------
    private function validar(Request $request, $id = null): array
    {
        return $request->validate([
            'nombre_insumo'  => ['required', 'string', 'max:100', Rule::unique(Insumo::class, 'nombre_insumo')->ignore($id, 'id_insumo')],
            'unidad_medida'  => ['required', 'string', 'max:20'],
            'stock_actual'   => ['required', 'numeric', 'min:0'],
            'stock_minimo'   => ['required', 'numeric', 'min:0'],
            'costo_unitario' => ['required', 'numeric', 'min:0'],
        ]);
    }
}
