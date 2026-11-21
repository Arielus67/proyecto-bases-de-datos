<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use App\Support\Sp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Mesas del salón. Escrituras por sp_mesa_insertar / actualizar / eliminar.
 * El estado OCUPADA lo maneja el módulo de mesero (sp_registrar_pedido), no este formulario.
 */
class MesaController extends Controller
{
    public function index()
    {
        $items = Mesa::orderBy('numero_mesa')->paginate(15);

        return view('admin.mesas.index', compact('items'));
    }

    public function create()
    {
        return view('admin.mesas.form', ['item' => null]);
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);

        return $this->intentar(
            fn () => Sp::insertar('sp_mesa_insertar', $datos, 'id_mesa'),
            'Mesa creada correctamente.',
            'admin.mesas.index'
        );
    }

    public function edit($id)
    {
        return view('admin.mesas.form', ['item' => Mesa::findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        $datos = ['id_mesa' => (int) $id] + $this->validar($request, $id);

        return $this->intentar(
            fn () => Sp::ejecutar('sp_mesa_actualizar', $datos),
            'Mesa actualizada correctamente.',
            'admin.mesas.index'
        );
    }

    public function destroy($id)
    {
        return $this->intentar(
            fn () => Sp::ejecutar('sp_mesa_eliminar', ['id_mesa' => (int) $id]),
            'Mesa eliminada correctamente.',
            'admin.mesas.index'
        );
    }

    private function validar(Request $request, $id = null): array
    {
        return $request->validate([
            'numero_mesa' => ['required', 'integer', 'min:1', 'max:9999', Rule::unique(Mesa::class, 'numero_mesa')->ignore($id, 'id_mesa')],
            'capacidad'   => ['required', 'integer', 'min:1', 'max:100'],
            'zona'        => ['required', 'string', 'max:50'],
            'estado'      => ['required', Rule::in(['DISPONIBLE', 'OCUPADA', 'RESERVADA', 'MANTENIMIENTO']), $this->reglaEstado($id)],
        ]);
    }

    // Una mesa con pedido abierto solo puede estar OCUPADA, y nadie la ocupa "a mano".
    private function reglaEstado($id): \Closure
    {
        return function (string $atributo, $valor, \Closure $fallar) use ($id) {
            $pedidoAbierto = $id === null ? null : DB::table('PEDIDO')
                ->where('id_mesa', $id)
                ->whereIn('estado', ['ABIERTO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO'])
                ->value('id_pedido');

            if ($pedidoAbierto && $valor !== 'OCUPADA') {
                $fallar('La mesa tiene abierto el pedido #' . $pedidoAbierto . '. Factúralo o anúlalo antes de cambiar su estado.');
            }

            if (! $pedidoAbierto && $valor === 'OCUPADA') {
                $fallar('Una mesa solo queda ocupada al registrar un pedido desde el módulo de mesero.');
            }
        };
    }
}
