<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * Cifrado de la cédula del cliente (columna CLIENTE.cedula VARBINARY(256)).
 *
 * Usa AES-256-CBC con una llave derivada de APP_KEY. El resultado binario es:
 *   [16 bytes de IV] + [texto cifrado]
 *
 * Si tu base de datos cifra con ENCRYPTBYKEY (llave simétrica de SQL Server),
 * reemplaza estos métodos por llamadas a ese cifrado.
 */
class CedulaCipher
{
    // ---------- Llave de 32 bytes derivada de APP_KEY ----------
    private static function llave(): string
    {
        return hash('sha256', (string) config('app.key'), true);
    }

    // ---------- Cifrar: texto plano -> binario ----------
    public static function cifrar(string $plano): string
    {
        $iv = random_bytes(16);

        return $iv . openssl_encrypt($plano, 'aes-256-cbc', self::llave(), OPENSSL_RAW_DATA, $iv);
    }

    // ---------- Descifrar: binario -> texto plano (null si no se puede) ----------
    public static function descifrar($binario): ?string
    {
        if (! is_string($binario) || strlen($binario) < 17) {
            return null;
        }

        $resultado = openssl_decrypt(substr($binario, 16), 'aes-256-cbc', self::llave(), OPENSSL_RAW_DATA, substr($binario, 0, 16));

        return $resultado === false ? null : $resultado;
    }

    // ---------- Máscara para mostrar en pantalla: •••••6789 ----------
    public static function enmascarar($binario): string
    {
        $plano = self::descifrar($binario);

        if ($plano === null) {
            return '••••••••';
        }

        return str_repeat('•', max(strlen($plano) - 4, 0)) . substr($plano, -4);
    }

    // ---------- Valor listo para insertar en una columna VARBINARY de SQL Server ----------
    // Se envía como hexadecimal y SQL Server lo convierte (evita errores de nvarchar -> varbinary).
    public static function paraSql(string $plano): Expression
    {
        return DB::raw("CONVERT(VARBINARY(256), '0x" . bin2hex(self::cifrar($plano)) . "', 1)");
    }
}
