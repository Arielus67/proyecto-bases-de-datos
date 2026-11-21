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
    public function test_los_scripts_no_tienen_comentarios(string $archivo): void
    {
        foreach ($this->lineas($archivo) as $numero => $linea) {
            $this->assertStringNotContainsString('--', $linea, "{$archivo}:" . ($numero + 1));
            $this->assertStringNotContainsString('/*', $linea, "{$archivo}:" . ($numero + 1));
        }
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

    public function test_todas_las_restricciones_de_las_tablas_tienen_nombre(): void
    {
        $sinNombre = [];

        foreach ($this->lineas('1_RestauranteDB_estructura.sql') as $numero => $linea) {
            if (preg_match('/^\s*(IF|CREATE (UNIQUE )?INDEX|WHERE)\b/i', $linea)) {
                continue;
            }

            $total = preg_match_all('/\b(PRIMARY KEY|UNIQUE|REFERENCES|CHECK|DEFAULT)\b/', $linea);
            $nombradas = preg_match_all('/CONSTRAINT \w+ (PRIMARY KEY|UNIQUE|REFERENCES|CHECK|DEFAULT)\b/', $linea);

            if ($total !== $nombradas) {
                $sinNombre[] = ($numero + 1) . ': ' . trim($linea);
            }
        }

        $this->assertSame([], $sinNombre);
    }

    public function test_los_nombres_de_restricciones_no_se_repiten_sin_distinguir_mayusculas(): void
    {
        preg_match_all('/CONSTRAINT (\w+) (?:PRIMARY KEY|UNIQUE|REFERENCES|CHECK|DEFAULT|FOREIGN KEY)\b/i', implode("\n", $this->lineas('1_RestauranteDB_estructura.sql')), $coincidencias);
        $repetidos = array_keys(array_filter(array_count_values(array_map('strtoupper', $coincidencias[1])), fn ($n) => $n > 1));

        $this->assertNotEmpty($coincidencias[1]);
        $this->assertSame([], $repetidos);
    }
}
