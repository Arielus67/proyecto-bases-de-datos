<?php

namespace App\Http\Controllers;

use App\Support\Sp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Clientes para facturación. Escrituras por sp_cliente_insertar / actualizar / eliminar.
 *
 * La cédula se guarda cifrada (AES-256, llave simétrica de SQL Server). La aplicación nunca la
 * cifra ni la descifra: la lee ya descifrada de la vista vw_cliente, que solo funciona para quien
 * tiene permiso sobre el certificado (el administrador). Que no se repita lo garantiza la base con
 * la huella cedula_hash, y el mensaje del procedimiento llega tal cual al formulario.
 */
class ClienteController extends Controller
{
    public function index()
    {
        $items = $this->clientes()->orderBy('apellido')->orderBy('nombre')->paginate(15);

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
        return view('admin.clientes.form', ['item' => $this->clientes()->where('id_cliente', (int) $id)->first() ?? abort(404)]);
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

    private function clientes()
    {
        return DB::connection('sqlsrv')->table('vw_cliente');
    }

    private function validar(Request $request, $id = null): array
    {
        return $request->validate([
            'cedula'   => ['required', 'string', 'max:20', 'regex:/^[0-9A-Za-z -]+$/'],
            'nombre'   => ['required', 'string', 'max:50'],
            'apellido' => ['required', 'string', 'max:50'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'correo'   => ['nullable', 'email', 'max:100'],
        ]);
    }
}
