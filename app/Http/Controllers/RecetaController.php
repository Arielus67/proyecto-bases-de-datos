<?php

namespace App\Http\Controllers;

use App\Models\Insumo;
use App\Models\Producto;
use App\Support\Sp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Recetas (tabla RECETA, relación N:M entre PRODUCTO e INSUMO, clave compuesta).
 * Se maneja la receta completa de un producto: el parámetro {receta} de las rutas es el id_producto.
 * Escrituras por sp_receta_insertar / sp_receta_eliminar dentro de una sola transacción.
 */
class RecetaController extends Controller
{
    // ---------- Listado: productos que tienen receta ----------
    public function index()
    {
        $items = Producto::has('recetas')
            ->with('recetas.insumo')
            ->orderBy('nombre_producto')
            ->paginate(15);

        return view('admin.recetas.index', compact('items'));
    }

    // ---------- Crear (solo productos sin receta) ----------
    public function create()
    {
        $productos = Producto::doesntHave('recetas')->orderBy('nombre_producto')->pluck('nombre_producto', 'id_producto');

        return view('admin.recetas.form', ['item' => null, 'productos' => $productos, 'insumos' => $this->insumos()]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'id_producto' => ['required', 'integer', 'exists:PRODUCTO,id_producto'],
        ] + $this->reglasLineas($request));

        if (DB::table('RECETA')->where('id_producto', $datos['id_producto'])->exists()) {
            return back()->withInput()->with('error', 'Ese producto ya tiene receta. Edítala desde el listado.');
        }

        return $this->intentar(
            fn () => $this->guardarLineas((int) $datos['id_producto'], $datos['insumos'], $datos['cantidades']),
            'Receta creada correctamente.',
            'admin.recetas.index'
        );
    }

    public function edit($id)
    {
        $item = Producto::with('recetas')->findOrFail($id);

        return view('admin.recetas.form', ['item' => $item, 'productos' => [], 'insumos' => $this->insumos()]);
    }

    // ---------- Actualizar: se reemplazan todas las líneas ----------
    public function update(Request $request, $id)
    {
        $producto = Producto::findOrFail($id);
        $datos = $request->validate($this->reglasLineas($request));

        return $this->intentar(
            fn () => $this->guardarLineas((int) $producto->id_producto, $datos['insumos'], $datos['cantidades']),
            'Receta actualizada correctamente.',
            'admin.recetas.index'
        );
    }

    // ---------- Eliminar la receta completa del producto ----------
    public function destroy($id)
    {
        return $this->intentar(
            fn () => Sp::ejecutar('sp_receta_eliminar', ['id_producto' => (int) $id]),
            'Receta eliminada correctamente.',
            'admin.recetas.index'
        );
    }

    // ---------- Insumos para el select: id => "nombre (unidad)" ----------
    private function insumos()
    {
        return Insumo::orderBy('nombre_insumo')->get()
            ->mapWithKeys(fn ($i) => [$i->id_insumo => $i->nombre_insumo . ' (' . $i->unidad_medida . ')']);
    }

    // ---------- Reglas de las líneas dinámicas (insumos[] y cantidades[]) ----------
    private function reglasLineas(Request $request): array
    {
        return [
            'insumos'      => ['required', 'array', 'min:1'],
            'insumos.*'    => ['required', 'integer', 'distinct', 'exists:INSUMO,id_insumo'],
            'cantidades'   => ['required', 'array', 'size:' . count((array) $request->input('insumos', []))],
            'cantidades.*' => ['required', 'numeric', 'decimal:0,2', 'max:99999999.99', 'min:0.01'],
        ];
    }

    // ---------- Borra la receta anterior e inserta la nueva, todo o nada ----------
    private function guardarLineas(int $idProducto, array $insumos, array $cantidades): void
    {
        $insumos = array_values($insumos);
        $cantidades = array_values($cantidades);

        DB::transaction(function () use ($idProducto, $insumos, $cantidades) {
            Sp::ejecutar('sp_receta_eliminar', ['id_producto' => $idProducto]);

            foreach ($insumos as $i => $idInsumo) {
                Sp::ejecutar('sp_receta_insertar', [
                    'id_producto' => $idProducto,
                    'id_insumo'   => (int) $idInsumo,
                    'cantidad'    => $cantidades[$i],
                ]);
            }
        });
    }
}
