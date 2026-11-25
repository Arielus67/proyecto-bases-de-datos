<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ScriptsSqlTest extends TestCase
{
    public static function scripts(): array
    {
        return [
            'estructura' => ['1_RestauranteDB_estructura.sql'],
            'programacion' => ['2_RestauranteDB_programacion.sql'],
            'datos iniciales' => ['3_datos_iniciales.sql'],
        ];
    }

    private function lineas(string $archivo): array
    {
        $texto = file_get_contents(dirname(__DIR__, 2) . '/database/sql/' . $archivo);
        $sinCadenas = preg_replace("/N?'(?:[^']|'')*'/", "''", $texto);

        return array_map('rtrim', explode("\n", str_replace("\r\n", "\n", $sinCadenas)));
    }

    #[DataProvider('scripts')]
    public function test_los_scripts_crean_los_objetos_sin_validar_si_existen(string $archivo): void
    {
        $texto = implode("
", $this->lineas($archivo));

        $this->assertDoesNotMatchRegularExpression('/(OBJECT_ID|DB_ID|DATABASE_PRINCIPAL_ID|IS_ROLEMEMBER|COL_LENGTH)\s*\(/i', preg_replace('/^CREATE (PROCEDURE|FUNCTION|TRIGGER|VIEW).*?^GO$/ms', '', $texto));
        $this->assertSame(
            $archivo === '2_RestauranteDB_programacion.sql' ? ['sp_restaurar_restaurante'] : [],
            preg_match_all('/CREATE OR ALTER PROCEDURE dbo\.(\w+)/', $texto, $m) ? $m[1] : []
        );
    }

    #[DataProvider('scripts')]
    public function test_se_conservan_los_punto_y_coma_obligatorios(string $archivo): void
    {
        $codigo = array_values(array_filter($this->lineas($archivo), fn ($l) => trim($l) !== ''));

        foreach ($codigo as $i => $linea) {
            $limpia = trim($linea);

            if (preg_match('/^(BEGIN TRAN|COMMIT|ROLLBACK)/i', $limpia) || preg_match('/\b(BEGIN|COMMIT|ROLLBACK) TRANSACTION$/i', $limpia)) {
                $this->assertStringEndsWith(';', $limpia, "{$archivo}: falta ; en «{$limpia}»");
            }

            $sangria = fn (string $l) => strlen($l) - strlen(ltrim($l));

            if ($i > 0 && preg_match('/^THROW\b/i', $limpia) && $sangria($codigo[$i - 1]) >= $sangria($linea)) {
                $this->assertStringEndsWith(';', trim($codigo[$i - 1]), "{$archivo}: la instrucción antes de «{$limpia}» debe terminar en ;");
            }
        }

        $this->assertNotEmpty($codigo);
    }

    public function test_las_llaves_foraneas_tienen_nombre_y_no_se_repiten(): void
    {
        $texto = implode("
", $this->lineas('1_RestauranteDB_estructura.sql'));

        preg_match_all('/CONSTRAINT (\w+)\s+FOREIGN KEY/', $texto, $nombradas);
        $this->assertSame(substr_count($texto, 'FOREIGN KEY'), count($nombradas[1]));
        $this->assertSame(substr_count($texto, 'REFERENCES'), count($nombradas[1]));
        $this->assertNotEmpty($nombradas[1]);

        $repetidos = array_keys(array_filter(array_count_values(array_map('strtoupper', $nombradas[1])), fn ($n) => $n > 1));
        $this->assertSame([], $repetidos);
    }

    public function test_se_conservan_los_indices_que_usa_la_aplicacion(): void
    {
        $texto = implode("
", $this->lineas('1_RestauranteDB_estructura.sql'));

        $this->assertMatchesRegularExpression('/CREATE UNIQUE INDEX UQ_TURNO_CAJA_Abierto ON TURNO_CAJA \(id_empleado\)\s+WHERE fecha_cierre IS NULL;/', $texto);
        $this->assertStringContainsString('CREATE UNIQUE INDEX UQ_PEDIDO_MesaAbierta ON PEDIDO (id_mesa)', $texto);
    }
}
