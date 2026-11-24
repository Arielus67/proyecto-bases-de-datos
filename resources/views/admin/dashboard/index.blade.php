@extends('layouts.app')

@section('titulo', 'Dashboard')

@php
    $crc = fn ($valor) => '₡' . number_format((float) $valor, 2, ',', ' ');
    $cifra = fn ($valor) => number_format((float) $valor, 2, ',', ' ');

    // Los enlaces solo aparecen si el rol tiene permiso: un rol que solo ve el dashboard no debe toparse con un 403
    $puedeVerPedidos = \App\Support\Acceso::permite(route('admin.pedidos.index'));

    $ventas = collect($ventasPorDia ?? [])->values();
    $totales = $ventas->map(fn ($v) => (float) data_get($v, 'total', 0))->values();
    $fechas = $ventas->map(fn ($v) => \Carbon\Carbon::parse(data_get($v, 'fecha'))->locale('es'));
    $etiquetas = $fechas->map(fn ($f, $i) => $i === $fechas->count() - 1 ? 'Hoy' : ucfirst($f->translatedFormat('D d')))->values();

    $ventasHoy = (float) ($stats['ventas_hoy'] ?? 0);
    $ventasAyer = $totales->count() > 1 ? $totales[$totales->count() - 2] : null;
    $variacionAyer = $ventasAyer ? ($ventasHoy - $ventasAyer) / $ventasAyer * 100 : null;

    $totalSemana = $totales->sum();
    $promedioDia = $totales->count() ? $totalSemana / $totales->count() : 0;
    $variacionPromedio = $promedioDia > 0 ? ($ventasHoy - $promedioDia) / $promedioDia * 100 : null;
    $indiceMejor = $totales->count() && $totales->max() > 0 ? $totales->search($totales->max()) : null;
    $mejorDia = $indiceMejor !== null ? ucfirst($fechas[$indiceMejor]->translatedFormat('l')) : null;

    $maxTotal = max(1.0, (float) $totales->max());
    $puntos = $totales->map(function ($total, $i) use ($totales, $maxTotal) {
        $x = $totales->count() > 1 ? round($i * 100 / ($totales->count() - 1), 2) : 0;
        $y = round(36 - ($total / $maxTotal) * 32, 2);

        return $x . ',' . $y;
    })->implode(' ');

    $listaMesas = collect($mesas ?? []);
    $totalMesas = $listaMesas->count();
    $mesasOcupadas = (int) ($stats['mesas_ocupadas'] ?? 0);
    $pctOcupacion = $totalMesas > 0 ? min(100, (int) round($mesasOcupadas / $totalMesas * 100)) : 0;

    $alertas = collect($alertasStock ?? []);
    $alertasPendientes = (int) ($stats['alertas_pendientes'] ?? 0);
    $alertasCriticas = $alertas->where('nivel', 'critico')->count();

    $productos = collect($topProductos ?? []);
    $unidadesTop = max(1, (int) $productos->sum('cantidad'));
    $maxProducto = max(1, (int) $productos->max('cantidad'));

    $pedidosAbiertos = (int) ($stats['pedidos_abiertos'] ?? 0);
    $pedidosRecientes = collect($ultimosPedidos ?? []);

    $hora = (int) now()->format('G');
    $saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');

    if ($ventasHoy <= 0 && $pedidosAbiertos === 0) {
        $resumen = 'Todavía no hay movimiento hoy. Los indicadores se actualizan con cada pedido y cobro.';
    } elseif ($variacionPromedio !== null && $variacionPromedio >= 0) {
        $resumen = 'Hoy vas ' . number_format($variacionPromedio, 0) . '% por encima del promedio de la semana.';
    } elseif ($variacionPromedio !== null) {
        $resumen = 'Hoy vas ' . number_format(abs($variacionPromedio), 0) . '% por debajo del promedio de la semana.';
    } else {
        $resumen = 'Así va el servicio en este momento.';
    }

    $estados = [
        'abierto' => 'Abierto',
        'en_preparacion' => 'En preparación',
        'listo' => 'Listo',
        'entregado' => 'Entregado',
        'facturado' => 'Facturado',
        'anulado' => 'Anulado',
    ];
@endphp

@section('contenido')
    <div class="tablero">
        <header class="tablero__cabecera">
            <div class="tablero__presentacion">
                <p class="tablero__fecha">
                    <span class="en-vivo" aria-hidden="true"></span>
                    Servicio en vivo · {{ ucfirst(now()->locale('es')->translatedFormat('l j \d\e F')) }} · {{ now()->format('H:i') }}
                </p>
                <h2 class="tablero__saludo">{{ $saludo }}, {{ auth()->user()->nombre }}</h2>
                <p class="tablero__resumen">{{ $resumen }}</p>
            </div>

            <nav class="tablero__accesos" aria-label="Accesos rápidos">
                @accesible(route('admin.pedidos.index'))
                    <a href="{{ route('admin.pedidos.index') }}" class="acceso-rapido"><i class="ti ti-receipt"></i> Pedidos</a>
                @endif
                @accesible(route('admin.facturas.index'))
                    <a href="{{ route('admin.facturas.index') }}" class="acceso-rapido"><i class="ti ti-file-invoice"></i> Facturas</a>
                @endif
                @accesible(route('admin.productos.index'))
                    <a href="{{ route('admin.productos.index') }}" class="acceso-rapido"><i class="ti ti-soup"></i> Carta</a>
                @endif
            </nav>
        </header>

        <section class="tablero__kpis" aria-label="Indicadores del día">
            <article class="kpi kpi--principal">
                <div class="kpi__cabecera">
                    <span class="kpi__etiqueta"><i class="ti ti-cash"></i> Ventas de hoy</span>
                    @if ($variacionAyer !== null)
                        <span class="variacion {{ $variacionAyer >= 0 ? 'variacion--sube' : 'variacion--baja' }}" title="Comparado con ayer">
                            <i class="ti {{ $variacionAyer >= 0 ? 'ti-trending-up' : 'ti-trending-down' }}"></i>
                            {{ $variacionAyer >= 0 ? '+' : '' }}{{ number_format($variacionAyer, 1, ',', ' ') }}%
                        </span>
                    @endif
                </div>

                <p class="kpi__valor kpi__valor--grande num">₡<span data-odometro>{{ $cifra($ventasHoy) }}</span></p>

                <div class="kpi__comparaciones">
                    <span>Ayer <strong class="num">{{ $ventasAyer !== null ? $crc($ventasAyer) : '—' }}</strong></span>
                    <span>Promedio 7 días <strong class="num">{{ $crc($promedioDia) }}</strong></span>
                </div>

                @if ($totales->count() > 1)
                    <svg class="kpi__tendencia" viewBox="0 0 100 40" preserveAspectRatio="none" aria-hidden="true">
                        <polygon points="0,40 {{ $puntos }} 100,40"></polygon>
                        <polyline points="{{ $puntos }}"></polyline>
                    </svg>
                @endif
            </article>

            <article class="kpi">
                <div class="kpi__cabecera">
                    <span class="kpi__etiqueta"><i class="ti ti-receipt-2"></i> Pedidos abiertos</span>
                </div>
                <p class="kpi__valor num"><span data-odometro>{{ $pedidosAbiertos }}</span></p>
                <p class="kpi__nota">{{ $pedidosAbiertos === 1 ? 'Pedido en curso' : 'Pedidos en curso' }}, sin facturar</p>
                @accesible(route('admin.pedidos.index'))
                    <a href="{{ route('admin.pedidos.index') }}" class="kpi__enlace">Ver pedidos <i class="ti ti-arrow-right"></i></a>
                @endif
            </article>

            <article class="kpi">
                <div class="kpi__cabecera">
                    <span class="kpi__etiqueta"><i class="ti ti-armchair"></i> Ocupación del salón</span>
                </div>
                <div class="kpi__ocupacion">
                    <div class="anillo" style="--valor: {{ $pctOcupacion }}" role="img" aria-label="{{ $pctOcupacion }}% de ocupación">
                        <svg viewBox="0 0 44 44" aria-hidden="true">
                            <circle class="anillo__pista" cx="22" cy="22" r="18"/>
                            <circle class="anillo__progreso" cx="22" cy="22" r="18" pathLength="100"/>
                        </svg>
                        <span class="anillo__valor num">{{ $pctOcupacion }}%</span>
                    </div>
                    <div>
                        <p class="kpi__valor num"><span data-odometro>{{ $mesasOcupadas }}</span><small>/{{ $totalMesas }}</small></p>
                        <p class="kpi__nota">{{ max(0, $totalMesas - $mesasOcupadas) }} mesas disponibles</p>
                    </div>
                </div>
            </article>

            <article class="kpi {{ $alertasPendientes > 0 ? 'kpi--alerta' : '' }}">
                <div class="kpi__cabecera">
                    <span class="kpi__etiqueta"><i class="ti ti-alert-triangle"></i> Inventario</span>
                </div>
                <p class="kpi__valor num"><span data-odometro>{{ $alertasPendientes }}</span></p>
                @if ($alertasPendientes > 0)
                    <p class="kpi__nota kpi__nota--alerta">
                        {{ $alertasPendientes === 1 ? 'Alerta de stock sin atender' : 'Alertas de stock sin atender' }}{{ $alertasCriticas ? ' · ' . $alertasCriticas . ' crítica' . ($alertasCriticas > 1 ? 's' : '') : '' }}
                    </p>
                @else
                    <p class="kpi__nota kpi__nota--ok"><i class="ti ti-circle-check"></i> Stock en orden</p>
                @endif
                @accesible(route('admin.alertas.index'))
                    <a href="{{ route('admin.alertas.index') }}" class="kpi__enlace">Ver alertas <i class="ti ti-arrow-right"></i></a>
                @endif
            </article>
        </section>

        <section class="tablero__fila tablero__fila--ventas">
            <article class="panel panel--grafico" aria-labelledby="titulo-grafico">
                <header class="panel__cabecera">
                    <div>
                        <h3 class="panel__titulo" id="titulo-grafico">Ventas de los últimos 7 días</h3>
                        <p class="panel__subtitulo">Facturas cobradas por día · hoy resaltado</p>
                    </div>
                    <div class="segmentado" role="group" aria-label="Tipo de gráfico">
                        <button type="button" class="segmentado__opcion activo dash-grafico__boton" data-tipo="bar" aria-pressed="true"><i class="ti ti-chart-bar"></i> Barras</button>
                        <button type="button" class="segmentado__opcion dash-grafico__boton" data-tipo="line" aria-pressed="false"><i class="ti ti-chart-line"></i> Línea</button>
                    </div>
                </header>

                <dl class="cifras">
                    <div><dt>Total de la semana</dt><dd class="num">{{ $crc($totalSemana) }}</dd></div>
                    <div><dt>Promedio diario</dt><dd class="num">{{ $crc($promedioDia) }}</dd></div>
                    <div><dt>Mejor día{{ $mejorDia ? ' · ' . $mejorDia : '' }}</dt><dd class="num">{{ $crc($totales->max() ?? 0) }}</dd></div>
                </dl>

                @if ($totales->isNotEmpty() && $totalSemana > 0)
                    <div class="panel__grafico">
                        <canvas id="grafico-ganancias" role="img" aria-label="Gráfico de ventas de los últimos siete días"></canvas>
                    </div>
                @else
                    <div class="vacio">
                        <i class="ti ti-chart-bar-off"></i>
                        <p>No hay ventas cobradas en los últimos 7 días.</p>
                    </div>
                @endif
            </article>

            <article class="panel panel--salon" aria-labelledby="titulo-salon">
                <header class="panel__cabecera">
                    <div>
                        <h3 class="panel__titulo" id="titulo-salon">Salón en vivo</h3>
                        <p class="panel__subtitulo">{{ $mesasOcupadas }} ocupadas · {{ max(0, $totalMesas - $mesasOcupadas) }} libres</p>
                    </div>
                    @accesible(route('admin.mesas.index'))
                        <a href="{{ route('admin.mesas.index') }}" class="panel__enlace">Mesas <i class="ti ti-arrow-right"></i></a>
                    @endif
                </header>

                @if ($listaMesas->isNotEmpty())
                    <ul class="plano">
                        @foreach ($listaMesas as $mesa)
                            @php $ocupada = data_get($mesa, 'estado') === 'ocupada'; @endphp
                            <li class="plano__mesa {{ $ocupada ? 'plano__mesa--ocupada' : '' }}" title="Mesa {{ data_get($mesa, 'numero_mesa') }} · {{ $ocupada ? 'Ocupada' : 'Libre' }}">
                                <i class="ti {{ $ocupada ? 'ti-users' : 'ti-armchair-2' }}" aria-hidden="true"></i>
                                <span class="num">{{ data_get($mesa, 'numero_mesa') }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="plano__leyenda">
                        <span><span class="punto punto--ocupada"></span> Ocupada</span>
                        <span><span class="punto punto--libre"></span> Libre</span>
                    </div>
                @else
                    <div class="vacio">
                        <i class="ti ti-armchair-off"></i>
                        <p>No hay mesas registradas.</p>
                    </div>
                @endif
            </article>
        </section>

        <section class="tablero__fila tablero__fila--detalle">
            <article class="panel panel--pedidos" aria-labelledby="titulo-pedidos">
                <header class="panel__cabecera">
                    <div>
                        <h3 class="panel__titulo" id="titulo-pedidos">Pedidos recientes</h3>
                        <p class="panel__subtitulo">Los últimos {{ $pedidosRecientes->count() }} pedidos registrados</p>
                    </div>
                    @accesible(route('admin.pedidos.index'))
                        <a href="{{ route('admin.pedidos.index') }}" class="panel__enlace">Ver todos <i class="ti ti-arrow-right"></i></a>
                    @endif
                </header>

                <div class="filtros">
                    <label class="filtros__busqueda">
                        <i class="ti ti-search" aria-hidden="true"></i>
                        <input type="search" id="buscar-pedido" placeholder="Buscar por pedido, mesa o mesero" autocomplete="off" aria-label="Buscar pedidos">
                    </label>
                    <select id="filtrar-estado" class="campo__control filtros__estado" aria-label="Filtrar por estado">
                        <option value="todos">Todos los estados</option>
                        @foreach ($pedidosRecientes->pluck('estado')->filter()->unique() as $estado)
                            <option value="{{ strtolower($estado) }}">{{ $estados[strtolower($estado)] ?? ucfirst(strtolower(str_replace('_', ' ', $estado))) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="pedidos__desplazable">
                    <table class="pedidos">
                        <thead>
                            <tr>
                                <th>Pedido</th>
                                <th>Mesa</th>
                                <th>Mesero</th>
                                <th>Hora</th>
                                <th class="pedidos__monto">Subtotal</th>
                                <th>Estado</th>
                                <th><span class="sr-solo">Acciones</span></th>
                            </tr>
                        </thead>
                        <tbody id="lista-pedidos">
                            @forelse ($pedidosRecientes as $pedido)
                                @php
                                    $estado = strtolower(trim((string) $pedido->estado));
                                    $fecha = \Carbon\Carbon::parse($pedido->fecha_hora)->locale('es');
                                @endphp
                                <tr data-estado="{{ $estado }}" data-busqueda="{{ strtolower('#' . $pedido->id_pedido . ' ' . ($pedido->mesa->numero_mesa ?? '') . ' ' . ($pedido->empleado->nombre ?? '') . ' ' . $pedido->estado) }}">
                                    <td><span class="pedidos__numero num">#{{ $pedido->id_pedido }}</span></td>
                                    <td><span class="pedidos__mesa"><i class="ti ti-armchair"></i> {{ $pedido->mesa->numero_mesa ?? '—' }}</span></td>
                                    <td>
                                        <span class="pedidos__mesero">
                                            <span class="pedidos__avatar">{{ mb_strtoupper(mb_substr($pedido->empleado->nombre ?? '?', 0, 1)) }}</span>
                                            {{ $pedido->empleado->nombre ?? '—' }}
                                        </span>
                                    </td>
                                    <td><span class="pedidos__hora" title="{{ $fecha->format('d/m/Y H:i') }}">{{ $fecha->isToday() ? $fecha->format('H:i') : $fecha->format('d/m H:i') }}</span></td>
                                    <td class="pedidos__monto num">{{ $crc($pedido->subtotal) }}</td>
                                    <td><span class="estado estado--{{ $estado }}">{{ $estados[$estado] ?? ucfirst(str_replace('_', ' ', $estado)) }}</span></td>
                                    <td>
                                        @if ($puedeVerPedidos)
                                            <a href="{{ route('admin.pedidos.show', $pedido->id_pedido) }}" class="boton-icono" title="Ver pedido" aria-label="Ver pedido {{ $pedido->id_pedido }}">
                                                <i class="ti ti-arrow-up-right"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">
                                        <div class="vacio"><i class="ti ti-receipt-off"></i><p>No hay pedidos recientes.</p></div>
                                    </td>
                                </tr>
                            @endforelse
                            <tr id="sin-resultados" hidden>
                                <td colspan="7"><div class="vacio"><i class="ti ti-search-off"></i><p>Ningún pedido coincide con la búsqueda.</p></div></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <footer class="panel__pie" id="contador-pedidos">Mostrando {{ $pedidosRecientes->count() }} pedidos</footer>
            </article>

            <div class="tablero__columna">
                <article class="panel" aria-labelledby="titulo-ranking">
                    <header class="panel__cabecera">
                        <div>
                            <h3 class="panel__titulo" id="titulo-ranking">Lo más vendido</h3>
                            <p class="panel__subtitulo">Unidades en los últimos 30 días</p>
                        </div>
                    </header>

                    @if ($productos->isNotEmpty())
                        <ol class="ranking">
                            @foreach ($productos as $producto)
                                @php
                                    $cantidad = (int) data_get($producto, 'cantidad', 0);
                                    $participacion = round($cantidad / $unidadesTop * 100);
                                @endphp
                                <li class="ranking__item">
                                    <span class="ranking__posicion num">{{ $loop->iteration }}</span>
                                    <div class="ranking__cuerpo">
                                        <div class="ranking__fila">
                                            <span class="ranking__nombre">{{ data_get($producto, 'nombre', '—') }}</span>
                                            <span class="ranking__cantidad num">{{ $cantidad }} <small>· {{ $participacion }}%</small></span>
                                        </div>
                                        <span class="ranking__barra"><span style="--valor: {{ round($cantidad / $maxProducto * 100) }}%"></span></span>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <div class="vacio"><i class="ti ti-tools-kitchen-2"></i><p>Aún no hay ventas para mostrar.</p></div>
                    @endif
                </article>

                <article class="panel" aria-labelledby="titulo-inventario">
                    <header class="panel__cabecera">
                        <div>
                            <h3 class="panel__titulo" id="titulo-inventario">Inventario por reponer</h3>
                            <p class="panel__subtitulo">Insumos con alerta sin atender</p>
                        </div>
                        @accesible(route('admin.insumos.index'))
                            <a href="{{ route('admin.insumos.index') }}" class="panel__enlace">Insumos <i class="ti ti-arrow-right"></i></a>
                        @endif
                    </header>

                    @if ($alertas->isNotEmpty())
                        <ul class="reponer">
                            @foreach ($alertas as $alerta)
                                @php $critico = ($alerta['nivel'] ?? '') === 'critico'; @endphp
                                <li class="reponer__item">
                                    <span class="reponer__icono {{ $critico ? 'reponer__icono--critico' : '' }}"><i class="ti {{ $critico ? 'ti-alert-octagon' : 'ti-alert-triangle' }}"></i></span>
                                    <div class="reponer__texto">
                                        <strong>{{ $alerta['nombre'] }}</strong>
                                        <small class="num">Quedan {{ rtrim(rtrim(number_format((float) $alerta['cantidad'], 2, ',', ' '), '0'), ',') }} {{ $alerta['unidad'] }}</small>
                                    </div>
                                    <span class="insignia {{ $critico ? 'insignia--red' : 'insignia--yellow' }}">{{ $critico ? 'Crítico' : 'Bajo' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="vacio vacio--ok"><i class="ti ti-circle-check"></i><p>Todo el inventario está sobre el mínimo.</p></div>
                    @endif
                </article>
            </div>
        </section>
    </div>
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('css/modulos/dashboard.css') }}">
@endpush

@push('scripts')
    @php
        $datosGrafico = ['etiquetas' => $etiquetas, 'totales' => $totales, 'promedio' => round($promedioDia, 2)];
    @endphp
    <script type="application/json" id="datos-dashboard">@json($datosGrafico)</script>
    @if ($totales->isNotEmpty() && $totalSemana > 0)
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    @endif
    <script src="{{ asset('js/modulos/dashboard.js') }}"></script>
@endpush
