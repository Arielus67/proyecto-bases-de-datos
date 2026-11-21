<?php

namespace App\Http\Controllers;

use App\Models\Insumo;
use App\Support\Sp;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Inventario de insumos. Escrituras por sp_insumo_insertar / actualizar / eliminar.
 * Las alertas de stock las crea y cierra el trigger trg_insumo_stock.
 */
class InsumoController extends Controller
{
    public function index()
    {
        $items = Insumo::orderBy('nombre_insumo')->paginate(15);

        return view('admin.insumos.index', compact('items'));
    }

    public function create()
    {
        return view('admin.insumos.form', ['item' => null]);
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);

        return $this->intentar(
            fn () => Sp::insertar('sp_insumo_insertar', $datos, 'id_insumo'),
            'Insumo creado correctamente.',
            'admin.insumos.index'
        );
    }

    public function edit($id)
    {
        return view('admin.insumos.form', ['item' => Insumo::findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        $datos = ['id_insumo' => (int) $id] + $this->validar($request, $id);

        return $this->intentar(
            fn () => Sp::ejecutar('sp_insumo_actualizar', $datos),
            'Insumo actualizado correctamente.',
            'admin.insumos.index'
        );
    }

    public function destroy($id)
    {
        return $this->intentar(
            fn () => Sp::ejecutar('sp_insumo_eliminar', ['id_insumo' => (int) $id]),
            'Insumo eliminado correctamente.',
            'admin.insumos.index'
        );
    }

    private function validar(Request $request, $id = null): array
    {
        return $request->validate([
            'nombre_insumo'  => ['required', 'string', 'max:100', Rule::unique(Insumo::class, 'nombre_insumo')->ignore($id, 'id_insumo')],
            'unidad_medida'  => ['required', 'string', 'max:20'],
            'stock_actual'   => ['required', 'numeric', 'decimal:0,2', 'max:99999999.99', 'min:0'],
            'stock_minimo'   => ['required', 'numeric', 'decimal:0,2', 'max:99999999.99', 'min:0'],
            'costo_unitario' => ['required', 'numeric', 'decimal:0,2', 'max:99999999.99', 'min:0'],
        ]);
    }
}
