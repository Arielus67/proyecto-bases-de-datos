<?php

namespace App\Http\Controllers;

use App\Models\MetodoPago;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MetodoPagoController extends Controller
{
    // ---------- Listado ----------
    public function index()
    {
        $items = MetodoPago::orderBy('nombre_metodo')->paginate(15);

        return view('modules.admin.metodos-pago.index', compact('items'));
    }

    // ---------- Formulario de creación ----------
    public function create()
    {
        return view('modules.admin.metodos-pago.form', ['item' => null]);
    }

    // ---------- Guardar nuevo ----------
    public function store(Request $request)
    {
        (new MetodoPago())->forceFill($this->validar($request))->save();

        return redirect()->route('admin.metodos-pago.index')->with('success', 'Método de pago creado correctamente.');
    }

    // ---------- Formulario de edición ----------
    public function edit($id)
    {
        return view('modules.admin.metodos-pago.form', ['item' => MetodoPago::findOrFail($id)]);
    }

    // ---------- Actualizar ----------
    public function update(Request $request, $id)
    {
        MetodoPago::findOrFail($id)->forceFill($this->validar($request, $id))->save();

        return redirect()->route('admin.metodos-pago.index')->with('success', 'Método de pago actualizado correctamente.');
    }

    // ---------- Eliminar ----------
    public function destroy($id)
    {
        try {
            MetodoPago::findOrFail($id)->delete();
        } catch (QueryException $e) {
            return back()->with('error', 'No se puede eliminar: el método tiene pagos asociados.');
        }

        return redirect()->route('admin.metodos-pago.index')->with('success', 'Método de pago eliminado correctamente.');
    }

    // ---------- Validación ----------
    private function validar(Request $request, $id = null): array
    {
        return $request->validate([
            'nombre_metodo' => ['required', 'string', 'max:50', Rule::unique(MetodoPago::class, 'nombre_metodo')->ignore($id, 'id_metodo_pago')],
        ]);
    }
}
