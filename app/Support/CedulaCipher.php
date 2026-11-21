<?php

namespace App\Support;

/**
 * Cifrado de la cédula del cliente (columna CLIENTE.cedula VARBINARY(256)).
 *
 * AES-256-CBC con una llave derivada de APP_KEY. Lo que se guarda es:
 *   [16 bytes de IV aleatorio] + [texto cifrado]
 * Como el IV cambia en cada cifrado, la misma cédula produce bytes distintos
 * cada vez (no se puede saber si dos clientes tienen la misma cédula mirando la tabla).
 *
 * La llave vive en el servidor web (.env), nunca en la base de datos: un respaldo
 * .bak robado no sirve para leer las cédulas.
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

    // ---------- Valor para sp_cliente_insertar / actualizar ----------
    // Se envía cifrado y en hexadecimal; el procedimiento lo convierte a VARBINARY
    // (el driver de PHP no puede enlazar binario directamente a un parámetro VARBINARY).
    public static function paraSp(string $plano): string
    {
        return bin2hex(self::cifrar($plano));
    }
}
