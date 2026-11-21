<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;

/**
 * Llamadas a los procedimientos almacenados de SQL Server, con parámetros por nombre.
 *
 *   Sp::ejecutar('sp_categoria_actualizar', ['id_categoria' => 3, 'nombre_categoria' => 'Bebidas']);
 *   $id    = Sp::insertar('sp_categoria_insertar', ['nombre_categoria' => 'Bebidas'], 'id_categoria');
 *   $filas = Sp::consultar('sp_categoria_consultar', ['id_categoria' => 3]);
 *
 * Los errores que lanza el procedimiento (THROW) llegan como excepción con el
 * mensaje escrito en SQL; el controlador lo muestra tal cual al usuario.
 */
final class Sp
{
    /** Procedimiento sin resultado: actualizar, eliminar. */
    public static function ejecutar(string $procedimiento, array $parametros = []): void
    {
        DB::connection('sqlsrv')->statement(self::sentencia($procedimiento, $parametros), self::valores($parametros));
    }

    /** Procedimiento que devuelve filas: consultar. */
    public static function consultar(string $procedimiento, array $parametros = []): array
    {
        return DB::connection('sqlsrv')->select(self::sentencia($procedimiento, $parametros), self::valores($parametros));
    }

    /** Procedimiento con un parámetro OUTPUT; devuelve el valor que dejó en él. */
    public static function salida(string $procedimiento, array $parametros, string $parametroSalida, string $tipoSql = 'INT'): mixed
    {
        $sql = "SET NOCOUNT ON; DECLARE @salida {$tipoSql}; "
            . self::sentencia($procedimiento, $parametros, $parametroSalida)
            . ' SELECT @salida AS salida;';

        $fila = DB::connection('sqlsrv')->selectOne($sql, self::valores($parametros));

        return $fila->salida ?? null;
    }

    /** Atajo para los insertar: devuelve el id generado. */
    public static function insertar(string $procedimiento, array $parametros, string $parametroId): int
    {
        return (int) self::salida($procedimiento, $parametros, $parametroId);
    }

    public static function lote(string $sql, array $parametros = [], string $conexion = 'sqlsrv'): ?object
    {
        $bd = DB::connection($conexion);
        $valores = self::valores($parametros);
        $fila = null;

        try {
            $sentencia = $bd->getPdo()->prepare($sql);
            $sentencia->execute($valores);

            do {
                if ($sentencia->columnCount() > 0) {
                    $actual = $sentencia->fetch(PDO::FETCH_OBJ);

                    if ($actual !== false) {
                        $fila = $actual;
                    }
                }
            } while (self::siguienteResultado($sentencia));

            $sentencia->closeCursor();
        } catch (PDOException $e) {
            throw new QueryException($bd->getName(), $sql, $valores, $e);
        }

        return $fila;
    }

    private static function siguienteResultado(\PDOStatement $sentencia): bool
    {
        try {
            return $sentencia->nextRowset();
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'IMSSP') && str_contains($e->getMessage(), 'no more results')) {
                return false;
            }

            throw $e;
        }
    }

    /** ¿La conexión es SQL Server? (las pruebas automáticas corren sobre SQLite, que no tiene procedimientos). */
    public static function disponibles(): bool
    {
        return DB::connection('sqlsrv')->getDriverName() === 'sqlsrv';
    }

    // ---------- EXEC dbo.sp_x @a = ?, @b = ?, @id = @salida OUTPUT; ----------
    private static function sentencia(string $procedimiento, array $parametros, ?string $parametroSalida = null): string
    {
        $partes = array_map(fn ($nombre) => "@{$nombre} = ?", array_keys($parametros));

        if ($parametroSalida !== null) {
            $partes[] = "@{$parametroSalida} = @salida OUTPUT";
        }

        return "EXEC dbo.{$procedimiento} " . implode(', ', $partes) . ';';
    }

    // ---------- Los booleanos de PHP viajan como 0/1 (columnas BIT) ----------
    private static function valores(array $parametros): array
    {
        return array_values(array_map(fn ($valor) => is_bool($valor) ? (int) $valor : $valor, $parametros));
    }
}
