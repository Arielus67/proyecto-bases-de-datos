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
        'FACTURA'  => 'Por factura (cada venta)',
        'DIA'      => 'Por día',
        'PRODUCTO' => 'Por producto',
        'MESERO'   => 'Por mesero',
        'PAGO'     => 'Por método de pago',
    ];

    /** Columnas con montos en colones (se suman en la fila de totales). */
    private const COLUMNAS_DINERO = ['Subtotal', 'Descuento', 'IVA', 'Servicio', 'Total', 'Subtotal_Acumulado',
        'IVA_Acumulado', 'Servicio_Acumulado', 'Venta_Total', 'Total_Recaudado', 'Total_Vendido'];

    /** Columnas con cantidades enteras (se suman en la fila de totales). */
    private const COLUMNAS_CANTIDAD = ['Total_Facturas', 'Unidades_Vendidas', 'Pedidos_Atendidos', 'Transacciones'];

    /** Columnas enteras que no tiene sentido sumar. */
    private const COLUMNAS_ENTERAS = ['Mesa', 'Ranking'];

    public function index(Request $request)
    {
        $datos = $request->validate([
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin'    => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'agrupar_por'  => ['nullable', 'in:' . implode(',', array_keys(self::AGRUPACIONES))],
        ]);

        $inicio = $datos['fecha_inicio'] ?? now(config('app.timezone'))->startOfMonth()->toDateString();
        $fin = $datos['fecha_fin'] ?? now(config('app.timezone'))->toDateString();
        $agrupar = $datos['agrupar_por'] ?? 'FACTURA';

        $filas = [];
        $error = null;

        try {
            // Fin inclusivo: hasta el último instante que guarda DATETIME en ese día (23:59:59.997)
            $filas = DB::connection('sqlsrv')->select(
                'EXEC sp_reporte_ventas @fecha_inicio = ?, @fecha_fin = ?, @agrupar_por = ?',
                [$inicio . ' 00:00:00', $fin . ' 23:59:59.997', $agrupar]
            );
        } catch (\Throwable $e) {
            report($e);
            $error = $this->mensajeSql($e);
        }

        $filas = collect($filas)->map(fn ($f) => (array) $f);
        $columnas = $filas->isEmpty() ? [] : array_keys($filas->first());

        $tipos = collect($columnas)->mapWithKeys(fn ($c) => [$c => match (true) {
            in_array($c, self::COLUMNAS_DINERO, true)    => 'dinero',
            in_array($c, self::COLUMNAS_CANTIDAD, true)  => 'cantidad',
            in_array($c, self::COLUMNAS_ENTERAS, true)   => 'entero',
            $c === 'Fecha_Hora'                          => 'fecha_hora',
            default                                      => 'texto',
        }])->all();

        $totales = collect($tipos)
            ->filter(fn ($tipo) => in_array($tipo, ['dinero', 'cantidad'], true))
            ->map(fn ($tipo, $c) => $filas->sum(fn ($f) => (float) $f[$c]))
            ->all();

        return view('admin.reportes.ventas', [
            'filas'        => $filas,
            'columnas'     => $columnas,
            'tipos'        => $tipos,
            'totales'      => $totales,
            'inicio'       => $inicio,
            'fin'          => $fin,
            'agrupar'      => $agrupar,
            'agrupaciones' => self::AGRUPACIONES,
            'error'        => $error,
        ]);
    }
}
