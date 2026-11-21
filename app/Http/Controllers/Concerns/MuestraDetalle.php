<?php

namespace App\Http\Controllers\Concerns;

/**
 * Vista de detalle genérica para los catálogos del administrador.
 * Muestra todos los campos del registro; los campos sensibles se indican en $ocultar.
 */
trait MuestraDetalle
{
    protected function mostrarDetalle(string $modelo, $id, string $titulo, string $volver, array $ocultar = [], ?callable $transformar = null)
    {
        $item = $modelo::findOrFail($id);

        if ($transformar !== null) {
            $transformar($item);
        }

        $campos = collect($item->getAttributes())
            ->except($ocultar)
            ->map(fn ($valor) => $valor === null ? '—' : (is_bool($valor) ? ($valor ? 'Sí' : 'No') : (string) $valor))
            ->all();

        return view('admin.detalle.show', compact('titulo', 'campos', 'volver'));
    }
}
