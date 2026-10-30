<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MesaController extends Controller
{
    // ---------- Listado ----------
    public function index()
    {
        $items = Mesa::orderBy('numero_mesa')->paginate(15);

        return view('modules.admin.mesas.index', compact('items'));
    }

    // ---------- Formulario de creación ----------
    public function create()
    {
        return view('modules.admin.mesas.form', ['item' => null]);
    }

    // ---------- Guardar nueva ----------
    public function store(Request $request)
    {
        (new Mesa())->forceFill($this->validar($request))->save();

        return redirect()->route('admin.mesas.index')->with('success', 'Mesa creada correctamente.');
    }

    // ---------- Formulario de edición ----------
    public function edit($id)
    {
        return view('modules.admin.mesas.form', ['item' => Mesa::findOrFail($id)]);
    }

    // ---------- Actualizar ----------
    public function update(Request $request, $id)
    {
        Mesa::findOrFail($id)->forceFill($this->validar($request, $id))->save();

        return redirect()->route('admin.mesas.index')->with('success', 'Mesa actualizada correctamente.');
    }

    // ---------- Eliminar ----------
    public function destroy($id)
    {
        try {
            Mesa::findOrFail($id)->delete();
        } catch (QueryException $e) {
            return back()->with('error', 'No se puede eliminar: la mesa tiene pedidos asociados. Puedes ponerla en mantenimiento.');
        }

        return redirect()->route('admin.mesas.index')->with('success', 'Mesa eliminada correctamente.');
    }

    // ---------- Validación ----------
    private function validar(Request $request, $id = null): array
    {
        return $request->validate([
            'numero_mesa' => ['required', 'integer', 'min:1', Rule::unique(Mesa::class, 'numero_mesa')->ignore($id, 'id_mesa')],
            'capacidad'   => ['required', 'integer', 'min:1'],
            'zona'        => ['required', 'string', 'max:50'],
            'estado'      => ['required', Rule::in(['DISPONIBLE', 'OCUPADA', 'RESERVADA', 'MANTENIMIENTO'])],
        ]);
    }
}
