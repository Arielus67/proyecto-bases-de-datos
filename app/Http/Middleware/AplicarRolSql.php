<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AplicarRolSql
{
    private const USUARIOS_SQL = [
        'mesero' => 'app_mesero',
        'cocinero' => 'app_cocinero',
        'cajero' => 'app_cajero',
    ];

    public function handle(Request $request, Closure $next, string $modulo): Response
    {
        $usuario = self::USUARIOS_SQL[$modulo] ?? null;
        $conexion = DB::connection('sqlsrv');

        if ($usuario === null || ! config('restaurante.impersonar_roles_sql') || $conexion->getDriverName() !== 'sqlsrv') {
            return $next($request);
        }

        $request->user()?->permisos();
        $conexion->unprepared("EXECUTE AS USER = N'" . $usuario . "'");

        try {
            return $next($request);
        } finally {
            $conexion->unprepared('REVERT');
        }
    }
}
