<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Estacion;
use App\Models\Producto;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductoController extends Controller
{
    // ---------- Listado ----------
    public function index()
    {
        $items = Producto::with(['categoria', 'estacion'])->orderBy('nombre_producto')->paginate(15);

        return view('modules.admin.productos.index', compact('items'));
    }

    // ---------- Formulario de creación ----------
    public function create()
    {
        return view('modules.admin.productos.form', ['item' => null] + $this->catalogos());
    }

    // ---------- Guardar nuevo ----------
    public function store(Request $request)
    {
        $datos = $this->validar($request);
        $datos['activo'] = $request->boolean('activo');

        (new Producto())->forceFill($datos)->save();

        return redirect()->route('admin.productos.index')->with('success', 'Producto creado correctamente.');
    }

    // ---------- Formulario de edición ----------
    public function edit($id)
    {
        return view('modules.admin.productos.form', ['item' => Producto::findOrFail($id)] + $this->catalogos());
    }

    // ---------- Actualizar ----------
    public function update(Request $request, $id)
    {
        $datos = $this->validar($request, $id);
        $datos['activo'] = $request->boolean('activo');

        Producto::findOrFail($id)->forceFill($datos)->save();

        return redirect()->route('admin.productos.index')->with('success', 'Producto actualizado correctamente.');
    }

    // ---------- Eliminar ----------
    public function destroy($id)
    {
        try {
            Producto::findOrFail($id)->delete();
        } catch (QueryException $e) {
            return back()->with('error', 'No se puede eliminar: el producto aparece en pedidos. Puedes desactivarlo editándolo.');
        }

        return redirect()->route('admin.productos.index')->with('success', 'Producto eliminado correctamente.');
    }

    // ---------- Listas para los select del formulario ----------
    private function catalogos(): array
    {
        return [
            'categorias' => Categoria::orderBy('nombre_categoria')->pluck('nombre_categoria', 'id_categoria'),
            'estaciones' => Estacion::orderBy('nombre_estacion')->pluck('nombre_estacion', 'id_estacion'),
        ];
    }

    // ---------- Validación ----------
    private function validar(Request $request, $id = null): array
    {
        return $request->validate([
            'nombre_producto' => ['required', 'string', 'max:100', Rule::unique(Producto::class, 'nombre_producto')->ignore($id, 'id_producto')],
            'descripcion'     => ['nullable', 'string', 'max:255'],
            'precio'          => ['required', 'numeric', 'min:0'],
            'id_categoria'    => ['required', 'integer', 'exists:CATEGORIA,id_categoria'],
            'id_estacion'     => ['required', 'integer', 'exists:ESTACION,id_estacion'],
        ]);
    }
}
