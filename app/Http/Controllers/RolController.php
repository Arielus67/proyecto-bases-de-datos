<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RolController extends Controller
{
    // ---------- Listado ----------
    public function index()
    {
        $items = Rol::orderBy('nombre_rol')->paginate(15);

        return view('modules.admin.roles.index', compact('items'));
    }

    // ---------- Formulario de creación ----------
    public function create()
    {
        return view('modules.admin.roles.form', ['item' => null]);
    }

    // ---------- Guardar nuevo ----------
    public function store(Request $request)
    {
        (new Rol())->forceFill($this->validar($request))->save();

        return redirect()->route('admin.roles.index')->with('success', 'Rol creado correctamente.');
    }

    // ---------- Formulario de edición ----------
    public function edit($id)
    {
        return view('modules.admin.roles.form', ['item' => Rol::findOrFail($id)]);
    }

    // ---------- Actualizar ----------
    public function update(Request $request, $id)
    {
        Rol::findOrFail($id)->forceFill($this->validar($request, $id))->save();

        return redirect()->route('admin.roles.index')->with('success', 'Rol actualizado correctamente.');
    }

    // ---------- Eliminar ----------
    public function destroy($id)
    {
        try {
            Rol::findOrFail($id)->delete();
        } catch (QueryException $e) {
            return back()->with('error', 'No se puede eliminar: el rol tiene empleados asociados.');
        }

        return redirect()->route('admin.roles.index')->with('success', 'Rol eliminado correctamente.');
    }

    // ---------- Validación ----------
    private function validar(Request $request, $id = null): array
    {
        return $request->validate([
            'nombre_rol' => ['required', 'string', 'max:50', Rule::unique(Rol::class, 'nombre_rol')->ignore($id, 'id_rol')],
        ]);
    }
}
