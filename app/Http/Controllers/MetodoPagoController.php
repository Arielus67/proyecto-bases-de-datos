<?php

namespace App\Http\Controllers;

use App\Models\MetodoPago;
use App\Support\Sp;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Catálogo de métodos de pago (efectivo, tarjeta, SINPE...).
 * Las lecturas usan Eloquent (paginación); las escrituras pasan por los
 * procedimientos sp_metodo_pago_insertar / actualizar / eliminar.
 */
class MetodoPagoController extends Controller
{
    public function index()
    {
        $items = MetodoPago::orderBy('nombre_metodo')->paginate(15);

        return view('admin.metodos-pago.index', compact('items'));
    }

    public function create()
    {
        return view('admin.metodos-pago.form', ['item' => null]);
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);

        return $this->intentar(
            fn () => Sp::insertar('sp_metodo_pago_insertar', $datos, 'id_metodo_pago'),
            'Método de pago creado correctamente.',
            'admin.metodos-pago.index'
        );
    }

    public function edit($id)
    {
        return view('admin.metodos-pago.form', ['item' => MetodoPago::findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        $datos = ['id_metodo_pago' => (int) $id] + $this->validar($request, $id);

        return $this->intentar(
            fn () => Sp::ejecutar('sp_metodo_pago_actualizar', $datos),
            'Método de pago actualizado correctamente.',
            'admin.metodos-pago.index'
        );
    }

    public function destroy($id)
    {
        return $this->intentar(
            fn () => Sp::ejecutar('sp_metodo_pago_eliminar', ['id_metodo_pago' => (int) $id]),
            'Método de pago eliminado correctamente.',
            'admin.metodos-pago.index'
        );
    }

    private function validar(Request $request, $id = null): array
    {
        return $request->validate([
            'nombre_metodo' => ['required', 'string', 'max:50', Rule::unique(MetodoPago::class, 'nombre_metodo')->ignore($id, 'id_metodo_pago')],
        ]);
    }
}
