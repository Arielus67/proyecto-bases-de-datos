<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Support\CedulaCipher;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClienteController extends Controller
{
    // ---------- Listado (la cédula se muestra enmascarada) ----------
    public function index()
    {
        $items = Cliente::orderBy('apellido')->orderBy('nombre')->paginate(15);

        $items->getCollection()->each(function ($cliente) {
            $cliente->cedula_mask = CedulaCipher::enmascarar($cliente->cedula);
        });

        return view('modules.admin.clientes.index', compact('items'));
    }

    // ---------- Formulario de creación ----------
    public function create()
    {
        return view('modules.admin.clientes.form', ['item' => null]);
    }

    // ---------- Guardar nuevo (la cédula se cifra antes de insertar) ----------
    public function store(Request $request)
    {
        $datos = $this->validar($request, true);

        DB::table('CLIENTE')->insert([
            'cedula'   => CedulaCipher::paraSql($datos['cedula']),
            'nombre'   => $datos['nombre'],
            'apellido' => $datos['apellido'],
            'telefono' => $datos['telefono'] ?? null,
            'correo'   => $datos['correo'] ?? null,
        ]);

        return redirect()->route('admin.clientes.index')->with('success', 'Cliente creado correctamente.');
    }

    // ---------- Formulario de edición ----------
    public function edit($id)
    {
        return view('modules.admin.clientes.form', ['item' => Cliente::findOrFail($id)]);
    }

    // ---------- Actualizar (si la cédula viene vacía se conserva la actual) ----------
    public function update(Request $request, $id)
    {
        Cliente::findOrFail($id);
        $datos = $this->validar($request, false);

        $cambios = [
            'nombre'   => $datos['nombre'],
            'apellido' => $datos['apellido'],
            'telefono' => $datos['telefono'] ?? null,
            'correo'   => $datos['correo'] ?? null,
        ];

        if (! empty($datos['cedula'])) {
            $cambios['cedula'] = CedulaCipher::paraSql($datos['cedula']);
        }

        DB::table('CLIENTE')->where('id_cliente', $id)->update($cambios);

        return redirect()->route('admin.clientes.index')->with('success', 'Cliente actualizado correctamente.');
    }

    // ---------- Eliminar ----------
    public function destroy($id)
    {
        try {
            Cliente::findOrFail($id)->delete();
        } catch (QueryException $e) {
            return back()->with('error', 'No se puede eliminar: el cliente tiene facturas asociadas.');
        }

        return redirect()->route('admin.clientes.index')->with('success', 'Cliente eliminado correctamente.');
    }

    // ---------- Validación (la cédula solo es obligatoria al crear) ----------
    private function validar(Request $request, bool $creando): array
    {
        return $request->validate([
            'cedula'   => [$creando ? 'required' : 'nullable', 'string', 'max:20'],
            'nombre'   => ['required', 'string', 'max:50'],
            'apellido' => ['required', 'string', 'max:50'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'correo'   => ['nullable', 'email', 'max:100'],
        ]);
    }
}
