<?php

namespace App\Support;

use App\Models\Empleado;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SesionUnica
{
    public const CLAVE = 'sesion_unica_token';

    public const ERROR_OCUPADA = 50200;

    public static function minutosInactividad(): int
    {
        return max(1, (int) config('restaurante.sesion_unica_minutos', 5));
    }

    public static function mensajeOcupada(): string
    {
        $minutos = self::minutosInactividad();

        return 'Esta cuenta ya tiene una sesión abierta en otro navegador o equipo. Cierra esa sesión con «Salir» '
            . 'o pide a un administrador que la cierre. Si se cerró sin salir, podrás entrar de nuevo en '
            . $minutos . ($minutos === 1 ? ' minuto.' : ' minutos.');
    }

    public static function abrir(Empleado $empleado, Request $request): bool
    {
        $token = Str::random(64);

        $parametros = [
            'id_empleado' => (int) $empleado->id_empleado,
            'token' => $token,
            'direccion_ip' => $request->ip(),
            'navegador' => Str::limit((string) $request->userAgent(), 250, ''),
            'minutos_inactividad' => self::minutosInactividad(),
        ];

        try {
            $abierta = Sp::disponibles()
                ? self::abrirConProcedimiento($parametros)
                : self::abrirConConsultas($parametros);
        } catch (QueryException $e) {
            if (self::esSesionOcupada($e)) {
                return false;
            }

            throw $e;
        }

        if ($abierta) {
            $request->session()->put(self::CLAVE, ['id_empleado' => (int) $empleado->id_empleado, 'token' => $token]);
        }

        return $abierta;
    }

    public static function validar(Empleado $empleado, Request $request): bool
    {
        $token = self::tokenDe($request, (int) $empleado->id_empleado);

        if ($token === null) {
            return self::abrir($empleado, $request);
        }

        if (Sp::disponibles()) {
            return (bool) Sp::salida('sp_sesion_validar', [
                'id_empleado' => (int) $empleado->id_empleado,
                'token' => $token,
            ], 'vigente', 'BIT');
        }

        $consulta = DB::connection('sqlsrv')->table('SESION_ACTIVA')
            ->where('id_empleado', $empleado->id_empleado)
            ->where('token', $token);

        if (! $consulta->exists()) {
            return false;
        }

        (clone $consulta)
            ->where('ultima_actividad', '<', now()->subSeconds(30)->toDateTimeString())
            ->update(['ultima_actividad' => now()->toDateTimeString()]);

        return true;
    }

    public static function cerrar(int $idEmpleado, ?string $token = null): void
    {
        if (Sp::disponibles()) {
            Sp::ejecutar('sp_sesion_cerrar', ['id_empleado' => $idEmpleado, 'token' => $token]);

            return;
        }

        DB::connection('sqlsrv')->table('SESION_ACTIVA')
            ->where('id_empleado', $idEmpleado)
            ->when($token !== null, fn ($consulta) => $consulta->where('token', $token))
            ->delete();
    }

    public static function cerrarActual(Request $request): void
    {
        $empleado = $request->user();

        if ($empleado) {
            $token = self::tokenDe($request, (int) $empleado->id_empleado);

            if ($token !== null) {
                self::cerrar((int) $empleado->id_empleado, $token);
            }
        }

        $request->session()->forget(self::CLAVE);
    }

    public static function faltaEstructura(QueryException $e): bool
    {
        $codigo = (int) ($e->errorInfo[1] ?? 0);

        return in_array($codigo, [208, 2812], true)
            || str_contains($e->getMessage(), 'no such table: SESION_ACTIVA');
    }

    private static function tokenDe(Request $request, int $idEmpleado): ?string
    {
        $guardado = $request->session()->get(self::CLAVE);

        if (! is_array($guardado) || (int) ($guardado['id_empleado'] ?? 0) !== $idEmpleado) {
            return null;
        }

        $token = $guardado['token'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    private static function abrirConProcedimiento(array $parametros): bool
    {
        Sp::ejecutar('sp_sesion_abrir', $parametros);

        return true;
    }

    private static function abrirConConsultas(array $parametros): bool
    {
        $bd = DB::connection('sqlsrv');

        return $bd->transaction(function () use ($bd, $parametros) {
            $bd->table('SESION_ACTIVA')
                ->where('id_empleado', $parametros['id_empleado'])
                ->where('ultima_actividad', '<', now()->subMinutes($parametros['minutos_inactividad'])->toDateTimeString())
                ->delete();

            if ($bd->table('SESION_ACTIVA')->where('id_empleado', $parametros['id_empleado'])->exists()) {
                return false;
            }

            $ahora = now()->toDateTimeString();

            $bd->table('SESION_ACTIVA')->insert([
                'id_empleado' => $parametros['id_empleado'],
                'token' => $parametros['token'],
                'direccion_ip' => $parametros['direccion_ip'],
                'navegador' => $parametros['navegador'],
                'fecha_inicio' => $ahora,
                'ultima_actividad' => $ahora,
            ]);

            return true;
        });
    }

    private static function esSesionOcupada(QueryException $e): bool
    {
        $codigo = (int) ($e->errorInfo[1] ?? 0);

        return $codigo === self::ERROR_OCUPADA
            || in_array($codigo, [2601, 2627], true)
            || str_contains($e->getMessage(), 'ya tiene una sesión abierta')
            || ($e->getCode() === '23000' && str_contains($e->getMessage(), 'SESION_ACTIVA'));
    }
}
