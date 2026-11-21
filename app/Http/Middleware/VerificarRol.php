<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerificarRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $empleado = $request->user();

        if (! $empleado || ! $empleado->activo) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['success' => false, 'error' => 'Sesión no válida.'], 401)
                : redirect()->route('login')->with('error', 'Tu usuario está inactivo o la sesión expiró.');
        }

        if (! $empleado->tieneRol(...$roles)) {
            abort(403, 'No tienes permiso para acceder a este módulo.');
        }

        return $next($request);
    }
}
