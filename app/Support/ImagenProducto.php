<?php

namespace App\Support;

use App\Models\Producto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImagenProducto
{
    public const EXTENSIONES = ['jpg', 'jpeg', 'png', 'webp'];

    public static function carpeta(): string
    {
        return trim(config('restaurante.imagenes_productos.carpeta', 'img/productos'), '/');
    }

    public static function carpetaPredeterminadas(): string
    {
        return trim(config('restaurante.imagenes_productos.predeterminadas', 'img/productos/predeterminadas'), '/');
    }

    public static function url(Producto $producto): ?string
    {
        $ruta = self::rutaSubida($producto) ?? self::rutaPredeterminada($producto);

        if ($ruta === null) {
            return null;
        }

        return asset($ruta) . '?v=' . filemtime(public_path($ruta));
    }

    public static function tieneSubida(Producto $producto): bool
    {
        return self::rutaSubida($producto) !== null;
    }

    public static function guardar(Producto $producto, UploadedFile $archivo): void
    {
        self::eliminar($producto);

        $extension = strtolower($archivo->extension() ?: $archivo->getClientOriginalExtension());
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;

        File::ensureDirectoryExists(public_path(self::carpeta()));
        $archivo->move(public_path(self::carpeta()), $producto->id_producto . '.' . $extension);
    }

    public static function eliminar(Producto $producto): void
    {
        foreach (self::EXTENSIONES as $extension) {
            File::delete(public_path(self::carpeta() . '/' . $producto->id_producto . '.' . $extension));
        }
    }

    private static function rutaSubida(Producto $producto): ?string
    {
        if (! $producto->id_producto) {
            return null;
        }

        foreach (self::EXTENSIONES as $extension) {
            $ruta = self::carpeta() . '/' . $producto->id_producto . '.' . $extension;

            if (is_file(public_path($ruta))) {
                return $ruta;
            }
        }

        return null;
    }

    private static function rutaPredeterminada(Producto $producto): ?string
    {
        $ruta = self::carpetaPredeterminadas() . '/' . Str::slug($producto->nombre_producto) . '.svg';

        return is_file(public_path($ruta)) ? $ruta : null;
    }
}
