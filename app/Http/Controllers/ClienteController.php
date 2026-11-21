<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Support\Sp;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Clientes para facturación. Escrituras por sp_cliente_insertar / actualizar / eliminar.
 */
class ClienteController extends Controller
{
    public function index()
    {
        $items = Cliente::orderBy('apellido')->orderBy('nombre')->paginate(15);

        return view('admin.clientes.index', compact('items'));
    }

    public function create()
    {
        return view('admin.clientes.form', ['item' => null]);
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);

        return $this->intentar(
            fn () => Sp::insertar('sp_cliente_insertar', $datos, 'id_cliente'),
            'Cliente creado correctamente.',
            'admin.clientes.index'
        );
    }

    public function edit($id)
    {
        return view('admin.clientes.form', ['item' => Cliente::findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        $datos = ['id_cliente' => (int) $id] + $this->validar($request, $id);

        return $this->intentar(
            fn () => Sp::ejecutar('sp_cliente_actualizar', $datos),
            'Cliente actualizado correctamente.',
            'admin.clientes.index'
        );
    }

    public function destroy($id)
    {
        return $this->intentar(
            fn () => Sp::ejecutar('sp_cliente_eliminar', ['id_cliente' => (int) $id]),
            'Cliente eliminado correctamente.',
            'admin.clientes.index'
        );
    }

    private function validar(Request $request, $id = null): array
    {
        return $request->validate([
            'cedula'   => ['required', 'string', 'max:20', Rule::unique('CLIENTE', 'cedula')->ignore($id, 'id_cliente')],
            'nombre'   => ['required', 'string', 'max:50'],
            'apellido' => ['required', 'string', 'max:50'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'correo'   => ['nullable', 'email', 'max:100'],
        ]);
    }
}
