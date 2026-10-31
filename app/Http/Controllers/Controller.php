<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /** Extrae solo el mensaje de SQL Server (sin la consulta ni los datos de conexión). */
    protected function mensajeSql(\Throwable $e): string
    {
        if (preg_match('/\[SQL Server\](.*?)\s*\(Connection:/s', $e->getMessage(), $m)) {
            return trim($m[1]);
        }

        return $e->getMessage();
    }
}
