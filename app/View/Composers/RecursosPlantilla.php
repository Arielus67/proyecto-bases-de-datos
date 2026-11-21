<?php

namespace App\View\Composers;

use Illuminate\View\View;

class RecursosPlantilla
{
    public function compose(View $vista): void
    {
        $fabrica = $vista->getFactory();
        $bienvenida = session()->pull('bienvenida', false);

        $estilos = [
            'css/sistema/tokens.css',
            'css/sistema/animaciones.css',
            'css/sistema/componentes.css',
            'css/sistema/utilidades-plantilla.css',
            'css/sistema/plantilla.css',
            'css/sistema/menu-lateral.css',
        ];

        if ($vista->name() === 'layouts.app') {
            $estilos[] = 'css/sistema/cristal.css';
        }

        if ($bienvenida) {
            $estilos[] = 'css/sistema/bienvenida.css';
        }

        $css = '<link rel="preconnect" href="https://fonts.googleapis.com">'
            . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;700&display=swap">';

        foreach ($estilos as $hoja) {
            $css .= '<link rel="stylesheet" href="' . e(asset($hoja)) . '?v=' . @filemtime(public_path($hoja)) . '">';
        }

        $fabrica->startPrepend('css', $css);

        $scripts = $bienvenida ? view('layouts.partials.bienvenida')->render() : '';
        $scripts .= '<div class="notificaciones" id="notificaciones" aria-live="polite"></div>';
        $scripts .= '<script src="' . e(asset('js/sistema/app.js')) . '?v=' . @filemtime(public_path('js/sistema/app.js')) . '"></script>';

        $fabrica->startPrepend('scripts', $scripts);
    }
}
