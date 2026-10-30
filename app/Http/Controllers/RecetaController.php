<?php

namespace App\Http\Controllers;

use App\Models\Insumo;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Recetas (tabla RECETA, relación N:M entre PRODUCTO e INSUMO, clave compuesta).
 * Se gestiona una receta completa por producto: el parámetro {receta} de las rutas es el id_producto.
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

        return view('modules.admin.recetas.index', compact('items'));
    }

    // ---------- Formulario de creación (solo productos sin receta) ----------
    public function create()
    {
        $productos = Producto::doesntHave('recetas')->orderBy('nombre_producto')->pluck('nombre_producto', 'id_producto');

        return view('modules.admin.recetas.form', ['item' => null, 'productos' => $productos, 'insumos' => $this->insumos()]);
    }

    // ---------- Guardar nueva receta ----------
    public function store(Request $request)
    {
        $datos = $request->validate([
            'id_producto' => ['required', 'integer', 'exists:PRODUCTO,id_producto'],
        ] + $this->reglasLineas());

        if (DB::table('RECETA')->where('id_producto', $datos['id_producto'])->exists()) {
            return back()->withInput()->with('error', 'Ese producto ya tiene receta. Edítala desde el listado.');
        }

        $this->guardarLineas($datos['id_producto'], $datos['insumos'], $datos['cantidades']);

        return redirect()->route('admin.recetas.index')->with('success', 'Receta creada correctamente.');
    }

    // ---------- Formulario de edición ----------
    public function edit($id)
    {
        $item = Producto::with('recetas')->findOrFail($id);

        return view('modules.admin.recetas.form', ['item' => $item, 'productos' => [], 'insumos' => $this->insumos()]);
    }

    // ---------- Actualizar (se reemplazan todas las líneas) ----------
    public function update(Request $request, $id)
    {
        $producto = Producto::findOrFail($id);
        $datos = $request->validate($this->reglasLineas());

        $this->guardarLineas($producto->id_producto, $datos['insumos'], $datos['cantidades']);

        return redirect()->route('admin.recetas.index')->with('success', 'Receta actualizada correctamente.');
    }

    // ---------- Eliminar la receta completa del producto ----------
    public function destroy($id)
    {
        DB::table('RECETA')->where('id_producto', $id)->delete();

        return redirect()->route('admin.recetas.index')->with('success', 'Receta eliminada correctamente.');
    }

    // ---------- Insumos para el select: id => "nombre (unidad)" ----------
    private function insumos()
    {
        return Insumo::orderBy('nombre_insumo')->get()
            ->mapWithKeys(fn ($i) => [$i->id_insumo => $i->nombre_insumo . ' (' . $i->unidad_medida . ')']);
    }

    // ---------- Reglas de las líneas dinámicas (insumos[] y cantidades[]) ----------
    private function reglasLineas(): array
    {
        return [
            'insumos'      => ['required', 'array', 'min:1'],
            'insumos.*'    => ['required', 'integer', 'distinct', 'exists:INSUMO,id_insumo'],
            'cantidades'   => ['required', 'array'],
            'cantidades.*' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    // ---------- Reemplaza las líneas de RECETA de un producto ----------
    private function guardarLineas($idProducto, array $insumos, array $cantidades): void
    {
        DB::transaction(function () use ($idProducto, $insumos, $cantidades) {
            DB::table('RECETA')->where('id_producto', $idProducto)->delete();

            $filas = [];
            foreach ($insumos as $i => $idInsumo) {
                $filas[] = [
                    'id_producto' => $idProducto,
                    'id_insumo'   => $idInsumo,
                    'cantidad'    => $cantidades[$i],
                ];
            }

            DB::table('RECETA')->insert($filas);
        });
    }
}
