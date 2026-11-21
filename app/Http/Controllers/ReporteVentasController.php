<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Reporte de ventas: delega en el procedimiento sp_reporte_ventas (la lógica de agrupación vive en SQL Server).
 */
class ReporteVentasController extends Controller
{
    private const AGRUPACIONES = [
        'DIA'      => 'Por día',
        'PRODUCTO' => 'Por producto',
        'MESERO'   => 'Por mesero',
        'PAGO'     => 'Por método de pago',
    ];

    public function index(Request $request)
    {
        $datos = $request->validate([
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin'    => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'agrupar_por'  => ['nullable', 'in:' . implode(',', array_keys(self::AGRUPACIONES))],
        ]);

        $inicio = $datos['fecha_inicio'] ?? now(config('app.timezone'))->startOfMonth()->toDateString();
        $fin = $datos['fecha_fin'] ?? now(config('app.timezone'))->toDateString();
        $agrupar = $datos['agrupar_por'] ?? 'DIA';

        $filas = [];
        $error = null;

        try {
            // Fin inclusivo: se toma hasta el final del día indicado
            $filas = DB::connection('sqlsrv')->select(
                'EXEC sp_reporte_ventas @fecha_inicio = ?, @fecha_fin = ?, @agrupar_por = ?',
                [$inicio . ' 00:00:00', $fin . ' 23:59:59', $agrupar]
            );
        } catch (\Throwable $e) {
            report($e);
            $error = $this->mensajeSql($e);
        }

        return view('admin.reportes.ventas', [
            'filas'        => collect($filas)->map(fn ($f) => (array) $f),
            'inicio'       => $inicio,
            'fin'          => $fin,
            'agrupar'      => $agrupar,
            'agrupaciones' => self::AGRUPACIONES,
            'error'        => $error,
        ]);
    }
}
