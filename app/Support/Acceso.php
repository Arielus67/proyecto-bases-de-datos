<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

final class Acceso
{
    public static function permite(?string $url, string $metodo = 'GET'): bool
    {
        $usuario = Auth::user();

        if ($url === null || $usuario === null) {
            return false;
        }

        try {
            $ruta = Route::getRoutes()->match(Request::create($url, $metodo));
        } catch (\Throwable) {
            return false;
        }

        foreach ($ruta->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'can:') && ! $usuario->can(substr($middleware, 4))) {
                return false;
            }
        }

        return true;
    }
}
