@extends('layouts.app')

@section('titulo', 'Dashboard')

@section('contenido')

    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">


   @php
    // Datos del gráfico
    $ventas = collect($ventasPorDia ?? []);

    $etiquetas = $ventas->map(
        fn ($v) => \Carbon\Carbon::parse(data_get($v, 'fecha'))->format('d/m')
    )->values();

    $totales = $ventas->map(
        fn ($v) => (float) data_get($v, 'total', 0)
    )->values();

    // Colecciones auxiliares
    $alertas = collect($alertasStock ?? []);
    $listaMesas = collect($mesas ?? []);
    $productos = collect($topProductos ?? []);

    $maxProducto = max(1, (int) $productos->max('cantidad'));

    // Resumen de ventas
    $cantidadDias = $totales->count();
    $totalSemana = $totales->sum();

    $promedioDia = $cantidadDias > 0
        ? $totalSemana / $cantidadDias
        : 0;

    // Sparkline
    $maxTotal = max(1.0, (float) $totales->max());

    $puntosLinea = $totales->map(function ($total, $i) use ($cantidadDias, $maxTotal) {
        $x = $cantidadDias > 1
            ? round($i * 100 / ($cantidadDias - 1), 2)
            : 0;

        $y = round(28 - ($total / $maxTotal) * 26, 2);

        return $x . ',' . $y;
    })->implode(' ');

    // Mesas
    $totalMesas = $listaMesas->count();
    $mesasOcupadas = (int) ($stats['mesas_ocupadas'] ?? 0);

    $pctOcupacion = $totalMesas > 0
        ? min(100, round($mesasOcupadas / $totalMesas * 100))
        : 0;

    // Alertas
    $hayAlertas = ($stats['alertas_pendientes'] ?? 0) > 0;
@endphp

    <div class="dash" id="dash">

        {{-- ========== CABECERA: título, fecha y acción principal ========== --}}
        <header class="dash__cabecera">
            <div>
                <h2 class="dash__titulo">Resumen del día</h2>
                <p class="dash__subtitulo">Así va el restaurante en este momento</p>
            </div>
            <div class="dash__acciones">
                <span class="dash__fecha">Actualizado {{ now()->format('d/m/Y H:i') }}</span>
                {{-- Route::has evita un error si la ruta todavía no existe --}}
                @if (Route::has('admin.pedidos.create'))
                    <a href="{{ route('admin.pedidos.create') }}" class="dash-boton">Nuevo pedido</a>
                @endif
            </div>
        </header>


        <div class="dash__matriz">

            {{-- ---------- VENTAS DE HOY (tarjeta principal) ---------- --}}

            <article class="dash-card dash-card--ventas dash-kpi">
                <div class="dash-kpi__top">
                    <div>
                        <span class="dash-kpi__etiqueta">Ventas de hoy</span>
                        <h3 class="dash-kpi__valor">
                            ₡{{ number_format($stats['ventas_hoy'] ?? 0, 2) }}
                        </h3>
                    </div>

                    <span class="dash-kpi__icono dash-kpi__icono--azul">
                        <i class="bi bi-currency-dollar"></i>
                    </span>
                </div>

                <div class="dash-kpi__pie">
                    <span class="dash-kpi__indicador dash-kpi__indicador--azul">
                        <i class="bi bi-graph-up-arrow"></i>
                        Ventas
                    </span>
                    <span class="dash-kpi__ayuda">Ingresos del día</span>
                </div>

                @if ($cantidadDias > 1)
                    <div class="dash-kpi__sparkline">
                        <svg viewBox="0 0 100 30" preserveAspectRatio="none">
                            <polygon points="{{ $puntosLinea }} 100,30 0,30"></polygon>
                            <polyline points="{{ $puntosLinea }}"></polyline>
                        </svg>
                    </div>
                @endif
            </article>

            {{-- ---------- PEDIDOS ABIERTOS ---------- --}}
            <article class="dash-card dash-card--pedidos dash-kpi">
                <div class="dash-kpi__top">
                    <div>
                        <span class="dash-kpi__etiqueta">Pedidos abiertos</span>
                        <h3 class="dash-kpi__valor">
                            {{ $stats['pedidos_abiertos'] ?? 0 }}
                        </h3>
                    </div>

                    <span class="dash-kpi__icono dash-kpi__icono--naranja">
                        <i class="bi bi-receipt"></i>
                    </span>
                </div>

                <div class="dash-kpi__pie">
                    <span class="dash-kpi__indicador dash-kpi__indicador--naranja">
                        <i class="bi bi-clock"></i>
                        En proceso
                    </span>
                    <span class="dash-kpi__ayuda">Pedidos pendientes</span>
                </div>
            </article>

            {{-- ---------- MESAS OCUPADAS ---------- --}}
            <article class="dash-card dash-card--mesas dash-kpi">
                <div class="dash-kpi__top">
                    <div>
                        <span class="dash-kpi__etiqueta">Mesas ocupadas</span>
                        <h3 class="dash-kpi__valor">
                            {{ $stats['mesas_ocupadas'] ?? 0 }}
                            <small>/ {{ $totalMesas }}</small>
                        </h3>
                    </div>

                    <span class="dash-kpi__icono dash-kpi__icono--verde">
                        <i class="bi bi-grid-3x3-gap"></i>
                    </span>
                </div>

                <div class="dash-kpi__progreso">
                    <div style="width: {{ $pctOcupacion }}%"></div>
                </div>

                <div class="dash-kpi__pie">
                    <span class="dash-kpi__indicador dash-kpi__indicador--verde">
                        {{ $pctOcupacion }}% de ocupación
                    </span>
                    <span class="dash-kpi__ayuda">
                        {{ max(0, $totalMesas - $mesasOcupadas) }} disponibles
                    </span>
                </div>
            </article>

            {{-- ---------- ALERTAS DE STOCK ---------- --}}
   <article class="dash-card dash-card--alertas dash-kpi">
    <div class="dash-kpi__top">
        <div>
            <span class="dash-kpi__etiqueta">Alertas de inventario</span>
            <h3 class="dash-kpi__valor">
                {{ $stats['alertas_pendientes'] ?? 0 }}
            </h3>
        </div>

        <span class="dash-kpi__icono dash-kpi__icono--rojo">
            <i class="bi bi-exclamation-triangle"></i>
        </span>
    </div>

    <div class="dash-kpi__pie">
        @if ($hayAlertas)
            <span class="dash-kpi__indicador dash-kpi__indicador--rojo">
                <i class="bi bi-exclamation-circle"></i>
                Requiere atención
            </span>
        @else
            <span class="dash-kpi__indicador dash-kpi__indicador--verde">
                <i class="bi bi-check-circle"></i>
                Todo en orden
            </span>
        @endif

        <span class="dash-kpi__ayuda">Control de stock</span>
    </div>

    {{-- Productos con stock bajo --}}
    <div class="dash-alertas-detalle">
        <button type="button"
                class="dash-alertas-toggle"
                data-bs-toggle="collapse"
                data-bs-target="#listaAlertasStock"
                aria-expanded="false">
            <span>
                <i class="bi bi-box-seam"></i>
                Ver productos afectados
            </span>

            <i class="bi bi-chevron-down"></i>
        </button>

        <div class="collapse" id="listaAlertasStock">
            <div class="dash-alertas-lista">
                @forelse($alertas as $alerta)
                    <div class="dash-alerta-item">
                        <div class="dash-alerta-info">
                            <strong>{{ $alerta['nombre'] }}</strong>
                            <small>
                                Stock actual:
                                {{ $alerta['cantidad'] }}
                                {{ $alerta['unidad'] }}
                            </small>
                        </div>

                        @if(($alerta['nivel'] ?? '') === 'critico')
                            <span class="dash-alerta-estado dash-alerta-estado--critico">
                                Crítico
                            </span>
                        @else
                            <span class="dash-alerta-estado dash-alerta-estado--bajo">
                                Bajo
                            </span>
                        @endif
                    </div>
                @empty
                    <div class="dash-alerta-vacio">
                        <i class="bi bi-check-circle"></i>
                        No hay productos con stock bajo.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</article>

            {{-- ---------- GRÁFICO DE GANANCIAS POR DÍA ---------- --}}

            {{-- GRÁFICO DE GANANCIAS --}}

            <section class="dash-card dash-card--grafico" aria-labelledby="titulo-grafico">

                <div class="dash-card__cabecera">

                    <div>
                        <h3 class="dash-card__titulo" id="titulo-grafico">
                            Rendimiento de ventas
                        </h3>

                        <p class="dash-card__subtitulo">
                            Análisis de ganancias de los últimos 7 días
                        </p>
                    </div>

                    <div class="dash-grafico__controles">

                        <button type="button" class="dash-grafico__boton activo" data-tipo="bar">
                            <i class="bi bi-bar-chart-fill"></i>
                            Barras
                        </button>

                        <button type="button" class="dash-grafico__boton" data-tipo="line">
                            <i class="bi bi-graph-up"></i>
                            Línea
                        </button>

                    </div>

                </div>

                <div class="dash-grafico__resumen">

                    <div>
                        <span>Ingresos totales</span>
                        <strong>
                            ₡{{ number_format($totalSemana, 2) }}
                        </strong>
                    </div>

                    <div>
                        <span>Promedio diario</span>
                        <strong>
                            ₡{{ number_format($promedioDia, 2) }}
                        </strong>
                    </div>

                    <div>
                        <span>Mejor día</span>
                        <strong>
                            @if ($totales->isNotEmpty())
                                ₡{{ number_format($totales->max(), 2) }}
                            @else
                                ₡0.00
                            @endif
                        </strong>
                    </div>

                </div>

                @if ($totales->isNotEmpty())

                    <div class="dash-grafico__lienzo dash-grafico__lienzo--mejorado">

                        <canvas id="grafico-ganancias" role="img"
                            aria-label="Gráfico interactivo de ganancias de los últimos siete días">
                        </canvas>

                    </div>

                @else

                    <p class="dash-vacio">
                        No hay ventas registradas para mostrar.
                    </p>

                @endif

            </section>


            {{-- ---------- ÚLTIMOS PEDIDOS ---------- --}}
            <section class="dash-card dash-card--tabla dash-tabla-card" aria-labelledby="titulo-pedidos">

                <div class="dash-card__cabecera dash-tabla__cabecera">
                    <div>
                        <h3 class="dash-card__titulo" id="titulo-pedidos">
                            Últimos pedidos
                        </h3>
                        <p class="dash-card__subtitulo">
                            Consulta y administra los pedidos recientes
                        </p>
                    </div>

                    @if (Route::has('admin.pedidos.index'))
                        <a href="{{ route('admin.pedidos.index') }}" class="dash-enlace">
                            Ver todos <i class="bi bi-arrow-right"></i>
                        </a>
                    @endif
                </div>

                <div class="dash-tabla__herramientas">
                    <div class="dash-tabla__busqueda">
                        <i class="bi bi-search"></i>
                        <input type="search" id="buscar-pedido" placeholder="Buscar pedido, mesa o mesero..."
                            autocomplete="off">
                    </div>

                    <select id="filtrar-estado" class="dash-tabla__filtro">
                        <option value="todos">Todos los estados</option>

                        @foreach (collect($ultimosPedidos ?? [])->pluck('estado')->filter()->unique() as $estado)
                            <option value="{{ strtolower($estado) }}">
                                {{ ucfirst(str_replace('_', ' ', $estado)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="dash-tabla__contenedor">
                    <table class="dash-tabla__tabla">
                        <thead>
                            <tr>
                                <th>Pedido</th>
                                <th>Mesa</th>
                                <th>Mesero</th>
                                <th>Fecha</th>
                                <th>Subtotal</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>

                        <tbody id="lista-pedidos">
                            @forelse ($ultimosPedidos ?? [] as $pedido)

                                                    @php
                                                        $estado = strtolower(trim((string) $pedido->estado));
                                                    @endphp

                                                    <tr data-estado="{{ $estado }}" data-busqueda="{{ strtolower(
                                    '#' . $pedido->id_pedido . ' ' .
                                    ($pedido->mesa->numero_mesa ?? '') . ' ' .
                                    ($pedido->empleado->nombre ?? '') . ' ' .
                                    $pedido->estado
                                ) }}">
                                                        <td>
                                                            <span class="dash-tabla__pedido">
                                                                #{{ $pedido->id_pedido }}
                                                            </span>
                                                        </td>

                                                        <td>
                                                            <span class="dash-tabla__mesa">
                                                                <i class="bi bi-grid"></i>
                                                                {{ $pedido->mesa->numero_mesa ?? '—' }}
                                                            </span>
                                                        </td>

                                                        <td>
                                                            <div class="dash-tabla__empleado">
                                                                <span class="dash-tabla__avatar">
                                                                    {{ strtoupper(substr($pedido->empleado->nombre ?? '?', 0, 1)) }}
                                                                </span>
                                                                {{ $pedido->empleado->nombre ?? '—' }}
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <span class="dash-tabla__fecha">
                                                                {{ \Carbon\Carbon::parse($pedido->fecha_hora)->format('d/m/Y H:i') }}
                                                            </span>
                                                        </td>

                                                        <td>
                                                            <strong class="dash-tabla__precio">
                                                                ₡{{ number_format($pedido->subtotal, 2) }}
                                                            </strong>
                                                        </td>

                                                        <td>
                                                            <span class="dash-estado dash-estado--{{ $estado }}">
                                                                {{ ucfirst(str_replace('_', ' ', $pedido->estado)) }}
                                                            </span>
                                                        </td>

                                                        <td>
                                                            <a href="{{ route('admin.pedidos.show', $pedido->id_pedido) }}"
                                                                class="dash-tabla__accion" title="Ver pedido"
                                                                aria-label="Ver pedido {{ $pedido->id_pedido }}">
                                                                <i class="bi bi-arrow-up-right"></i>
                                                            </a>
                                                        </td>
                                                    </tr>

                            @empty
                                <tr>
                                    <td colspan="7" class="dash-tabla__vacio">
                                        <i class="bi bi-receipt"></i>
                                        <span>No hay pedidos recientes.</span>
                                    </td>
                                </tr>
                            @endforelse

                            <tr id="sin-resultados" hidden>
                                <td colspan="7" class="dash-tabla__vacio">
                                    No se encontraron pedidos con esos criterios.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="dash-tabla__pie">
                    <span id="contador-pedidos">
                        Mostrando {{ count($ultimosPedidos ?? []) }} pedidos
                    </span>
                </div>
            </section>

            {{-- ---------- MAPA DE MESAS ---------- --}}
            <section class="dash-card dash-card--mapa" aria-labelledby="titulo-mesas">
                <div class="dash-card__cabecera">
                    <h3 class="dash-card__titulo" id="titulo-mesas">Estado de mesas</h3>
                </div>

                @if ($listaMesas->isNotEmpty())
                    <ul class="dash-mesas">
                        @foreach ($listaMesas as $mesa)
                            @php $ocupada = data_get($mesa, 'estado') === 'ocupada'; @endphp
                            <li class="dash-mesa {{ $ocupada ? 'dash-mesa--ocupada' : '' }}"
                                title="Mesa {{ data_get($mesa, 'numero_mesa') }} · {{ $ocupada ? 'Ocupada' : 'Libre' }}">
                                {{ data_get($mesa, 'numero_mesa') }}
                            </li>
                        @endforeach
                    </ul>
                    <div class="dash-leyenda">
                        <span class="dash-leyenda__item"><span
                                class="dash-leyenda__muestra dash-leyenda__muestra--ocupada"></span>Ocupada</span>
                        <span class="dash-leyenda__item"><span class="dash-leyenda__muestra"></span>Libre</span>
                    </div>
                @else
                    <p class="dash-vacio">No hay mesas registradas</p>
                @endif
            </section>

            {{-- ---------- PRODUCTOS MÁS VENDIDOS ---------- --}}
            <section class="dash-card dash-card--ranking" aria-labelledby="titulo-ranking">
                <div class="dash-card__cabecera">
                    <h3 class="dash-card__titulo" id="titulo-ranking">Productos más vendidos</h3>
                </div>

                @if ($productos->isNotEmpty())
                    <ol class="dash-ranking">
                        @foreach ($productos as $producto)
                            @php
                                $cantidad = (int) data_get($producto, 'cantidad', 0);
                                $porcentaje = round(($cantidad / $maxProducto) * 100);
                            @endphp
                            <li class="dash-ranking__item">
                                <span class="dash-ranking__posicion">{{ $loop->iteration }}</span>
                                <div class="dash-ranking__cuerpo">
                                    <div class="dash-ranking__fila">
                                        <span class="dash-ranking__nombre">{{ data_get($producto, 'nombre', '—') }}</span>
                                        <span class="dash-ranking__cantidad">{{ $cantidad }}</span>
                                    </div>
                                    <div class="dash-ranking__barra">
                                        <div class="dash-ranking__relleno" style="--valor: {{ $porcentaje }}%"></div>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="dash-vacio">Aún no hay ventas para mostrar</p>
                @endif
            </section>
        </div>

        {{-- Elemento oculto: permite leer el color real de "text-primary" del proyecto --}}
        <span id="dash-sonda" class="text-primary" hidden></span>
    </div>

    {{-- ========== SCRIPT ==========
    1) Sincroniza el color primario del CSS con el del proyecto.
    2) Dibuja el gráfico de barras con Chart.js (solo si hay datos). --}}
    @if ($totales->isNotEmpty())
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    @endif
    <script>

        (function () {

            const canvas = document.getElementById('grafico-ganancias');

            if (!canvas || typeof Chart === 'undefined') return;

            // Datos provenientes de Laravel
            const etiquetas = @json($etiquetas);
            const totales = @json($totales);

            if (!totales.length) return;

            const contexto = canvas.getContext('2d');

            // Formato de moneda costarricense
            const moneda = new Intl.NumberFormat('es-CR', {
                style: 'currency',
                currency: 'CRC',
                maximumFractionDigits: 0
            });

            // Degradado de las barras
            const degradado = contexto.createLinearGradient(0, 0, 0, 300);

            degradado.addColorStop(0, '#2563eb');
            degradado.addColorStop(1, 'rgba(37, 99, 235, 0.12)');

            // Promedio de ventas
            const promedio = totales.reduce((a, b) => a + b, 0)
                / totales.length;

            // Plugin personalizado para dibujar el promedio
            const lineaPromedio = {

                id: 'lineaPromedio',

                afterDatasetsDraw(chart) {

                    const { ctx, chartArea, scales } = chart;

                    if (!chartArea || !scales.y) return;

                    const posicion = scales.y.getPixelForValue(promedio);

                    if (posicion < chartArea.top ||
                        posicion > chartArea.bottom) return;

                    ctx.save();

                    ctx.beginPath();
                    ctx.setLineDash([6, 5]);
                    ctx.lineWidth = 2;
                    ctx.strokeStyle = '#f59e0b';

                    ctx.moveTo(chartArea.left, posicion);
                    ctx.lineTo(chartArea.right, posicion);

                    ctx.stroke();

                    ctx.setLineDash([]);
                    ctx.fillStyle = '#b45309';
                    ctx.font = '600 11px sans-serif';
                    ctx.textAlign = 'right';

                    ctx.fillText(
                        'Promedio: ' + moneda.format(promedio),
                        chartArea.right,
                        posicion - 8
                    );

                    ctx.restore();
                }
            };

            // Crear gráfico
            const grafico = new Chart(contexto, {

                type: 'bar',

                data: {

                    labels: etiquetas,

                    datasets: [{

                        label: 'Ganancias',

                        data: totales,

                        backgroundColor: degradado,

                        borderColor: '#2563eb',

                        borderWidth: 0,

                        borderRadius: 8,

                        borderSkipped: false,

                        maxBarThickness: 48,

                        hoverBackgroundColor: '#1d4ed8'
                    }]
                },

                plugins: [lineaPromedio],

                options: {

                    responsive: true,

                    maintainAspectRatio: false,

                    animation: {
                        duration: 900,
                        easing: 'easeOutQuart'
                    },

                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },

                    layout: {
                        padding: {
                            top: 20,
                            right: 12,
                            bottom: 5
                        }
                    },

                    plugins: {

                        legend: {
                            display: false
                        },

                        tooltip: {

                            backgroundColor: '#172033',

                            titleColor: '#ffffff',

                            bodyColor: '#e2e8f0',

                            padding: 14,

                            cornerRadius: 9,

                            displayColors: false,

                            callbacks: {

                                title: function (items) {
                                    return 'Fecha: ' + items[0].label;
                                },

                                label: function (item) {
                                    return 'Ganancias: ' +
                                        moneda.format(item.parsed.y);
                                }
                            }
                        }
                    },

                    scales: {

                        x: {

                            grid: {
                                display: false
                            },

                            border: {
                                display: false
                            },

                            ticks: {
                                color: '#64748b',
                                font: {
                                    size: 11,
                                    weight: '500'
                                }
                            }
                        },

                        y: {

                            beginAtZero: true,

                            border: {
                                display: false,
                                dash: [4, 4]
                            },

                            grid: {
                                color: '#edf0f5',
                                drawTicks: false
                            },

                            ticks: {

                                color: '#94a3b8',

                                padding: 12,

                                callback: function (valor) {

                                    return '₡' +
                                        Number(valor).toLocaleString('es-CR');
                                }
                            }
                        }
                    }
                }
            });


            // Cambiar entre gráfico de barras y líneas
            const botones = document.querySelectorAll(
                '.dash-grafico__boton'
            );

            botones.forEach(boton => {

                boton.addEventListener('click', function () {

                    const tipo = this.dataset.tipo;

                    botones.forEach(b => b.classList.remove('activo'));

                    this.classList.add('activo');

                    grafico.config.type = tipo;

                    const dataset = grafico.data.datasets[0];

                    if (tipo === 'line') {

                        dataset.backgroundColor = degradado;
                        dataset.borderColor = '#2563eb';
                        dataset.borderWidth = 3;
                        dataset.borderRadius = 0;
                        dataset.fill = true;
                        dataset.tension = 0.4;
                        dataset.pointRadius = 4;
                        dataset.pointHoverRadius = 7;
                        dataset.pointBackgroundColor = '#2563eb';
                        dataset.pointBorderColor = '#ffffff';
                        dataset.pointBorderWidth = 2;

                    } else {

                        dataset.backgroundColor = degradado;
                        dataset.borderWidth = 0;
                        dataset.borderRadius = 8;
                        dataset.fill = false;
                        dataset.tension = 0;
                        dataset.pointRadius = 0;

                    }

                    grafico.update();
                });
            });

        })();

    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const buscador = document.getElementById('buscar-pedido');
            const filtro = document.getElementById('filtrar-estado');
            const lista = document.getElementById('lista-pedidos');
            const contador = document.getElementById('contador-pedidos');
            const sinResultados = document.getElementById('sin-resultados');

            if (!buscador || !filtro || !lista) return;

            const filas = Array.from(
                lista.querySelectorAll('tr[data-estado]')
            );

            function filtrarPedidos() {
                const texto = buscador.value.toLowerCase().trim();
                const estado = filtro.value;

                let visibles = 0;

                filas.forEach(function (fila) {
                    const contenido = fila.dataset.busqueda || '';
                    const estadoPedido = fila.dataset.estado || '';

                    const coincideTexto = contenido.includes(texto);
                    const coincideEstado =
                        estado === 'todos' || estadoPedido === estado;

                    const mostrar = coincideTexto && coincideEstado;

                    fila.hidden = !mostrar;

                    if (mostrar) visibles++;
                });

                sinResultados.hidden = visibles !== 0;

                contador.textContent =
                    'Mostrando ' + visibles + ' de ' + filas.length + ' pedidos';
            }

            buscador.addEventListener('input', filtrarPedidos);
            filtro.addEventListener('change', filtrarPedidos);
        });
    </script>
@endsection