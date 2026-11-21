<?php

namespace App\Support;

class CodigoBarras
{
    private const ANCHO = 3;

    private const PATRONES = [
        '0' => '000110100', '1' => '100100001', '2' => '001100001', '3' => '101100000',
        '4' => '000110001', '5' => '100110000', '6' => '001110000', '7' => '000100101',
        '8' => '100100100', '9' => '001100100', 'A' => '100001001', 'B' => '001001001',
        'C' => '101001000', 'D' => '000011001', 'E' => '100011000', 'F' => '001011000',
        'G' => '000001101', 'H' => '100001100', 'I' => '001001100', 'J' => '000011100',
        'K' => '100000011', 'L' => '001000011', 'M' => '101000010', 'N' => '000010011',
        'O' => '100010010', 'P' => '001010010', 'Q' => '000000111', 'R' => '100000110',
        'S' => '001000110', 'T' => '000010110', 'U' => '110000001', 'V' => '011000001',
        'W' => '111000000', 'X' => '010010001', 'Y' => '110010000', 'Z' => '011010000',
        '-' => '010000101', '.' => '110000100', ' ' => '011000100', '*' => '010010100',
    ];

    public static function texto(string $valor): string
    {
        $limpio = strtoupper($valor);

        return preg_replace('/[^0-9A-Z\-. ]/', '', $limpio);
    }

    public static function code39(string $valor): array
    {
        $contenido = '*' . self::texto($valor) . '*';
        $barras = [];
        $x = 0;

        foreach (str_split($contenido) as $indice => $caracter) {
            if ($indice > 0) {
                $x += 1;
            }

            foreach (str_split(self::PATRONES[$caracter]) as $posicion => $ancho) {
                $unidades = $ancho === '1' ? self::ANCHO : 1;

                if ($posicion % 2 === 0) {
                    $barras[] = ['x' => $x, 'ancho' => $unidades];
                }

                $x += $unidades;
            }
        }

        return ['barras' => $barras, 'ancho' => $x];
    }
}
