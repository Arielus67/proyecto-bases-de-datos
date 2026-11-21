<?php

namespace App\Http\Controllers;

use App\Http\Middleware\VerificarSesionUnica;
use App\Support\SesionUnica;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function create(Request $request)
    {
        $aviso = $request->session()->pull(VerificarSesionUnica::AVISO);

        return $aviso
            ? view('auth.login')->withErrors(['correo' => $aviso])
            : view('auth.login');
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'correo' => ['required', 'email', 'max:100'],
            'contrasena' => ['required', 'string', 'max:100'],
        ]);

        $llave = Str::lower($datos['correo']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($llave, 5)) {
            throw ValidationException::withMessages([
                'correo' => 'Demasiados intentos. Intenta de nuevo en ' . RateLimiter::availableIn($llave) . ' segundos.',
            ]);
        }

        $credenciales = [
            'correo' => $datos['correo'],
            'password' => $datos['contrasena'],
            'activo' => 1,
        ];

        if (! Auth::attempt($credenciales)) {
            RateLimiter::hit($llave, 60);

            throw ValidationException::withMessages([
                'correo' => 'El correo o la contraseña no son correctos, o el usuario está inactivo.',
            ]);
        }

        RateLimiter::clear($llave);
        $request->session()->regenerate();

        $empleado = $request->user();

        if ($empleado->rutaInicio() === null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'correo' => 'Tu rol no tiene permisos asignados. Pide a un administrador que te los asigne.',
            ]);
        }

        try {
            $abierta = SesionUnica::abrir($empleado, $request);
        } catch (QueryException $e) {
            if (! SesionUnica::faltaEstructura($e)) {
                throw $e;
            }

            Log::warning('Falta SESION_ACTIVA o sus procedimientos; ejecute los scripts 1 y 2 de SQL', ['error' => $e->getMessage()]);
            $abierta = true;
        }

        if (! $abierta) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'correo' => SesionUnica::mensajeOcupada(),
            ]);
        }

        $request->session()->put('bienvenida', true);

        return redirect()->intended($empleado->rutaInicio());
    }

    public function inicio(Request $request)
    {
        $ruta = $request->user()->rutaInicio();

        if ($ruta === null) {
            return $this->destroy($request)->withErrors(['correo' => 'Tu rol no tiene permisos asignados. Pide a un administrador que te los asigne.']);
        }

        return redirect()->to($ruta);
    }

    public function destroy(Request $request)
    {
        SesionUnica::cerrarActual($request);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
