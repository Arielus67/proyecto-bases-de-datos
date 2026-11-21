<?php

namespace App\Http\Middleware;

use App\Support\SesionUnica;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerificarSesionUnica
{
    public const AVISO = 'aviso_sesion';

    public function handle(Request $request, Closure $next): Response
    {
        $empleado = $request->user();

        if (! $empleado) {
            return $next($request);
        }

        if (! $empleado->activo) {
            return $this->cerrar($request, 'Tu usuario está inactivo. Pide a un administrador que lo active.');
        }

        try {
            $vigente = SesionUnica::validar($empleado, $request);
        } catch (QueryException $e) {
            if (! SesionUnica::faltaEstructura($e)) {
                throw $e;
            }

            Log::warning('Falta SESION_ACTIVA o sus procedimientos; ejecute los scripts 1 y 2 de SQL', ['error' => $e->getMessage()]);

            return $next($request);
        }

        if ($vigente) {
            return $next($request);
        }

        return $this->cerrar($request, 'Tu sesión se cerró porque esta cuenta está abierta en otro navegador o un administrador la cerró.');
    }

    private function cerrar(Request $request, string $mensaje): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put(self::AVISO, $mensaje);

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json(['message' => $mensaje, 'redirect' => route('login')], 401);
        }

        return redirect()->route('login');
    }
}
