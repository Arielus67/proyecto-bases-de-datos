<?php

namespace App\Support;

use App\Models\BitacoraEventoNoSql;
use App\Models\Empleado;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Abre el turno de caja del cajero al iniciar sesión, sin depender del administrador.
 *
 * - Solo aplica a empleados registrados en CAJERO.
 * - Si ya tiene un turno abierto, lo reutiliza (no abre otro).
 * - El monto inicial es el fondo de caja configurado (CAJA_MONTO_INICIAL, 0 por defecto).
 * - Que no existan dos turnos abiertos lo sigue garantizando SQL Server
 *   (índice UQ_TURNO_CAJA_Abierto); si dos inicios de sesión compiten, el segundo reutiliza el turno.
 */
final class TurnoAutomatico
{
    /** Devuelve el id del turno abierto del cajero (creándolo si hacía falta) o null si no es cajero. */
    public static function abrirSiFalta(Empleado $empleado): ?int
    {
        $sql = DB::connection('sqlsrv');

        if (! $sql->table('CAJERO')->where('id_empleado', $empleado->id_empleado)->exists()) {
            return null;
        }

        $abierto = self::turnoAbierto($empleado);

        if ($abierto) {
            return $abierto;
        }

        $monto = round(max(0, (float) config('restaurante.caja_monto_inicial', 0)), 2);

        try {
            $idTurno = Sp::disponibles()
                ? Sp::insertar('sp_turno_caja_insertar', ['id_empleado' => (int) $empleado->id_empleado, 'monto_inicial' => $monto], 'id_turno')
                : (int) $sql->table('TURNO_CAJA')->insertGetId(['id_empleado' => $empleado->id_empleado, 'monto_inicial' => $monto, 'fecha_apertura' => now()], 'id_turno');
        } catch (\Throwable $e) {
            // Otro inicio de sesión lo abrió al mismo tiempo: se usa ese
            if ($abierto = self::turnoAbierto($empleado)) {
                return $abierto;
            }

            throw $e;
        }

        try {
            BitacoraEventoNoSql::create([
                'evento'     => 'TURNO_ABIERTO',
                'detalles'   => 'Turno #' . $idTurno . ' abierto automáticamente al iniciar sesión ' .
                    $empleado->nombre . ' ' . $empleado->apellido . ' (fondo ' . number_format($monto, 2) . ')',
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Turno abierto, pero falló la bitácora en MongoDB', ['turno' => $idTurno, 'error' => $e->getMessage()]);
        }

        return $idTurno;
    }

    private static function turnoAbierto(Empleado $empleado): ?int
    {
        $id = DB::connection('sqlsrv')->table('TURNO_CAJA')
            ->where('id_empleado', $empleado->id_empleado)
            ->whereNull('fecha_cierre')
            ->value('id_turno');

        return $id ? (int) $id : null;
    }
}
