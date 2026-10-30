<?php

namespace App\Http\Controllers;

use App\Models\Estacion;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EstacionController extends Controller
{
    // ---------- Listado ----------
    public function index()
    {
        $items = Estacion::orderBy('nombre_estacion')->paginate(15);

        return view('modules.admin.estaciones.index', compact('items'));
    }

    // ---------- Formulario de creación ----------
    public function create()
    {
        return view('modules.admin.estaciones.form', ['item' => null]);
    }

    // ---------- Guardar nueva ----------
    public function store(Request $request)
    {
        (new Estacion())->forceFill($this->validar($request))->save();

        return redirect()->route('admin.estaciones.index')->with('success', 'Estación creada correctamente.');
    }

    // ---------- Formulario de edición ----------
    public function edit($id)
    {
        return view('modules.admin.estaciones.form', ['item' => Estacion::findOrFail($id)]);
    }

    // ---------- Actualizar ----------
    public function update(Request $request, $id)
    {
        Estacion::findOrFail($id)->forceFill($this->validar($request, $id))->save();

        return redirect()->route('admin.estaciones.index')->with('success', 'Estación actualizada correctamente.');
    }

    // ---------- Eliminar ----------
    public function destroy($id)
    {
        try {
            Estacion::findOrFail($id)->delete();
        } catch (QueryException $e) {
            return back()->with('error', 'No se puede eliminar: la estación tiene productos o cocineros asociados.');
        }

        return redirect()->route('admin.estaciones.index')->with('success', 'Estación eliminada correctamente.');
    }

    // ---------- Validación ----------
    private function validar(Request $request, $id = null): array
    {
        return $request->validate([
            'nombre_estacion' => ['required', 'string', 'max:50', Rule::unique(Estacion::class, 'nombre_estacion')->ignore($id, 'id_estacion')],
        ]);
    }
}
