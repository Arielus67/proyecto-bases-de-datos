<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

abstract class Controller
{
    /**
     * Ejecuta una escritura (normalmente un procedimiento almacenado) y, si SQL Server
     * la rechaza, vuelve al formulario mostrando el mensaje del THROW tal cual.
     */
    protected function intentar(\Closure $operacion, string $mensajeExito, string $ruta, array $parametrosRuta = [])
    {
        try {
            $operacion();
        } catch (\Throwable $e) {
            Log::warning('Operación rechazada por la base de datos', ['error' => $e->getMessage()]);

            return back()->withInput()->with('error', $this->mensajeSql($e));
        }

        return redirect()->route($ruta, $parametrosRuta)->with('success', $mensajeExito);
    }

    /** Extrae solo el mensaje de SQL Server (sin la consulta ni los datos de conexión). */
    protected function mensajeSql(\Throwable $e): string
    {
        if (preg_match('/\[SQL Server\](.*?)\s*\(Connection:/s', $e->getMessage(), $m)) {
            return trim($m[1]);
        }

        return $e->getMessage();
    }

    protected function esDuplicado(\Throwable $e): bool
    {
        return $e instanceof UniqueConstraintViolationException
            || ($e instanceof QueryException && preg_match('/duplicate key|UNIQUE constraint failed/i', $e->getMessage()) === 1);
    }
}
