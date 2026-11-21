<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Estacion;
use App\Models\Producto;
use App\Support\Sp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Productos del menú. Escrituras por sp_producto_insertar / actualizar / eliminar.
 * La imagen se guarda como archivo en public/img/productos y su ruta en la columna PRODUCTO.foto.
 * Al eliminar, el trigger trg_producto_baja_logica decide si se borra de verdad
 * (nunca se vendió) o solo se desactiva (ya aparece en pedidos).
 */
class ProductoController extends Controller
{
    public function index()
    {
        $items = Producto::with(['categoria', 'estacion'])->orderBy('nombre_producto')->paginate(15);

        return view('admin.productos.index', compact('items'));
    }

    public function create()
    {
        return view('admin.productos.form', ['item' => null] + $this->catalogos());
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);
        $datos['activo'] = $request->boolean('activo');
        $datos['foto'] = $this->guardarFoto($request);

        return $this->intentar(
            fn () => Sp::insertar('sp_producto_insertar', $datos, 'id_producto'),
            'Producto creado correctamente.',
            'admin.productos.index'
        );
    }

    public function edit($id)
    {
        return view('admin.productos.form', ['item' => Producto::findOrFail($id)] + $this->catalogos());
    }

    public function update(Request $request, $id)
    {
        $producto = Producto::findOrFail($id);
        $datos = $this->validar($request, $id);
        $datos['activo'] = $request->boolean('activo');

        // Foto nueva, quitar la foto, o dejar la que tenía
        $datos['foto'] = $this->guardarFoto($request)
            ?? ($request->boolean('quitar_imagen') ? null : $producto->foto);

        return $this->intentar(function () use ($producto, $datos) {
            Sp::ejecutar('sp_producto_actualizar', ['id_producto' => $producto->id_producto] + $datos);

            if ($producto->foto !== $datos['foto']) {
                $this->borrarFoto($producto->foto);
            }
        }, 'Producto actualizado correctamente.', 'admin.productos.index');
    }

    public function destroy($id)
    {
        $producto = Producto::findOrFail($id);

        try {
            $desactivado = (bool) Sp::salida('sp_producto_eliminar', ['id_producto' => $producto->id_producto], 'desactivado', 'BIT');
        } catch (\Throwable $e) {
            return back()->with('error', $this->mensajeSql($e));
        }

        if ($desactivado) {
            return redirect()->route('admin.productos.index')
                ->with('success', 'El producto ya aparece en pedidos, así que se desactivó para conservar el historial.');
        }

        $this->borrarFoto($producto->foto);

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

    private function validar(Request $request, $id = null): array
    {
        $datos = $request->validate([
            'nombre_producto' => ['required', 'string', 'max:100', Rule::unique('PRODUCTO', 'nombre_producto')->ignore($id, 'id_producto')],
            'descripcion'     => ['nullable', 'string', 'max:255'],
            'precio'          => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'id_categoria'    => ['required', 'integer', 'exists:CATEGORIA,id_categoria'],
            'id_estacion'     => ['required', 'integer', 'exists:ESTACION,id_estacion'],
            'imagen'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'imagen.image' => 'El archivo debe ser una imagen.',
            'imagen.mimes' => 'La imagen debe ser JPG, PNG o WEBP.',
            'imagen.max'   => 'La imagen no puede pesar más de 2 MB.',
        ]);

        unset($datos['imagen']);   // el archivo no va al procedimiento; solo su ruta (foto)

        return $datos;
    }

    // ---------- Guarda la imagen subida en public/img/productos y devuelve su ruta ----------
    private function guardarFoto(Request $request): ?string
    {
        if (! $request->hasFile('imagen')) {
            return null;
        }

        $carpeta = config('restaurante.carpeta_fotos', 'img/productos');
        $nombre = Str::random(16) . '.' . $request->file('imagen')->extension();

        $request->file('imagen')->move(public_path($carpeta), $nombre);

        return $carpeta . '/' . $nombre;
    }

    // ---------- Borra el archivo de una foto (las ilustraciones predeterminadas no se borran) ----------
    private function borrarFoto(?string $foto): void
    {
        if ($foto && ! str_contains($foto, 'predeterminadas')) {
            File::delete(public_path($foto));
        }
    }
}
