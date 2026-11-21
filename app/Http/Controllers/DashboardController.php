<?php

namespace App\Http\Controllers;

use App\Models\AlertaStock;
use App\Models\DetallePedido;
use App\Models\Factura;
use App\Models\Mesa;
use App\Models\Pedido;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    // ---------- Resumen general del restaurante ----------
    public function index()
    {
        // Las 4 tarjetas de resumen (consultas simples, sin relaciones)
        $stats = [
            // Pedidos que todavía están en curso (ni facturados ni anulados)
            'pedidos_abiertos'   => Pedido::whereNotIn('estado', ['FACTURADO', 'ANULADO'])->count(),
            // Total cobrado hoy
            'ventas_hoy'         => Factura::where('estado', 'COBRADA')->whereDate('fecha_hora', today())->sum('total'),
            'mesas_ocupadas'     => Mesa::where('estado', 'OCUPADA')->count(),
            'alertas_pendientes' => AlertaStock::where('atendida', 0)->count(),
        ];

        // Tabla "Últimos pedidos"
        $ultimosPedidos = Pedido::with(['mesa', 'empleado'])
            ->orderByDesc('fecha_hora')
            ->limit(8)
            ->get();

        $ventasPorDia = $this->bloqueSeguro('ganancias por día', fn () => $this->ventasUltimosDias(7));
        $alertasStock = $this->bloqueSeguro('alertas de stock',  fn () => $this->alertasPendientes(5));
        $mesas        = $this->bloqueSeguro('estado de mesas',   fn () => $this->estadoDeMesas());
        $topProductos = $this->bloqueSeguro('productos más vendidos', fn () => $this->productosMasVendidos(5, 30));

        return view('admin.dashboard.index', compact(
            'stats',
            'ultimosPedidos',
            'ventasPorDia',
            'alertasStock',
            'mesas',
            'topProductos'
        ));
    }

    // ---------- Red de seguridad para cada bloque ----------
    // Ejecuta la consulta; si lanza un error lo guarda en storage/logs/laravel.log
    // (con el detalle exacto) y devuelve una lista vacía en lugar de romper la página.
    private function bloqueSeguro(string $nombre, callable $consulta): array
    {
        try {
            return $consulta();
        } catch (\Throwable $error) {
            report($error);
            Log::warning("Dashboard: no se pudo cargar el bloque '{$nombre}'.");

            return [];
        }
    }

    // ---------- Gráfico: total cobrado por día ----------
    // Devuelve siempre $dias elementos (incluye los días sin ventas con total 0)
    // para que el gráfico no tenga huecos en el eje X. El último elemento es hoy.
    private function ventasUltimosDias(int $dias): array
    {
        $desde = today()->subDays($dias - 1);

        // Una sola consulta agrupada por fecha (CAST funciona en MySQL, SQL Server y PostgreSQL)
        $totalesPorFecha = Factura::where('estado', 'COBRADA')
            ->whereDate('fecha_hora', '>=', $desde)
            ->select(DB::raw('CAST(fecha_hora AS DATE) as fecha'), DB::raw('SUM(total) as total'))
            ->groupBy(DB::raw('CAST(fecha_hora AS DATE)'))
            ->get()
            ->mapWithKeys(fn ($fila) => [Carbon::parse($fila->fecha)->toDateString() => (float) $fila->total]);

        // Recorremos del día más antiguo al de hoy y rellenamos con 0 los que no tienen ventas
        $resultado = [];
        for ($i = $dias - 1; $i >= 0; $i--) {
            $fecha = today()->subDays($i)->toDateString();
            $resultado[] = [
                'fecha' => $fecha,
                'total' => $totalesPorFecha[$fecha] ?? 0,
            ];
        }

        return $resultado;
    }

    // ---------- Alertas de stock sin atender ----------
    private function alertasPendientes(int $limite): array
    {
        return AlertaStock::with('insumo')
            ->where('atendida', 0)
            ->limit($limite)
            ->get()
            ->map(function ($alerta) {
                $insumo = $alerta->insumo;
                $actual = (float) ($insumo->stock_actual ?? 0);
                $minimo = (float) ($insumo->stock_minimo ?? 0);

                return [
                    'nombre'   => $insumo->nombre_insumo ?? 'Insumo sin nombre',
                    'cantidad' => $actual,
                    'unidad'   => $insumo->unidad_medida ?? '',
                    // Crítico si queda la mitad (o menos) del mínimo; si no, "bajo"
                    'nivel'    => $actual <= ($minimo / 2) ? 'critico' : 'bajo',
                ];
            })
            ->all();
    }

    // ---------- Mapa de mesas ----------
    // La vista solo distingue "ocupada" y "libre"; cualquier otro estado cuenta como libre.
    private function estadoDeMesas(): array
    {
        return Mesa::orderBy('numero_mesa')
            ->get()
            ->map(fn ($mesa) => [
                'numero_mesa' => $mesa->numero_mesa,
                'estado'      => $mesa->estado === 'OCUPADA' ? 'ocupada' : 'libre',
            ])
            ->all();
    }

    private function productosMasVendidos(int $limite, int $dias): array
    {
        return DetallePedido::select('id_producto', DB::raw('SUM(cantidad) as total_vendido'))
            ->whereHas('pedido', function ($consulta) use ($dias) {
                $consulta->where('estado', '!=', 'ANULADO')
                         ->whereDate('fecha_hora', '>=', today()->subDays($dias));
            })
            ->groupBy('id_producto')
            ->orderByDesc('total_vendido')
            ->with('producto')
            ->limit($limite)
            ->get()
            ->map(fn ($detalle) => [
                'nombre'   => $detalle->producto->nombre_producto ?? 'Producto',
                'cantidad' => (int) $detalle->total_vendido,
            ])
            ->all();
    }
}