<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Guarda un valor en la sesión de SQL Server (sp_set_session_context), que los triggers leen con SESSION_CONTEXT().
 *
 * El valor se escribe como literal y no como parámetro enlazado (?): sp_set_session_context
 * recibe un sql_variant y el driver sqlsrv de PHP lo rechaza al enlazar NULL o algunos tipos.
 */
class SesionSql
{
    public static function establecer(string $clave, int|string|null $valor): void
    {
        $conexion = DB::connection('sqlsrv');

        if ($conexion->getDriverName() !== 'sqlsrv') {
            return;
        }

        $literal = match (true) {
            $valor === null => 'NULL',
            is_int($valor) => (string) $valor,
            default => "N'" . str_replace("'", "''", (string) $valor) . "'",
        };

        $conexion->unprepared(
            "EXEC sp_set_session_context @key = N'" . str_replace("'", "''", $clave) . "', @value = {$literal}, @read_only = 0"
        );
    }
}
