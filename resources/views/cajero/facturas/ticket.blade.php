@php
    $ancho = request('ancho') === '58' ? 58 : 80;
    $recienEmitido = request()->boolean('nuevo');
    $fmt = fn ($v) => '₡' . number_format((float) $v, 2, ',', ' ');
    $fecha = \Carbon\Carbon::parse($factura->fecha_hora);
    $codigo = \App\Support\CodigoBarras::code39($factura->numero_factura);
    $textoCodigo = \App\Support\CodigoBarras::texto($factura->numero_factura);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    @section('titulo', 'Ticket ' . $factura->numero_factura)
    @include('layouts.partials.head')
    <link rel="stylesheet" href="{{ asset('css/modulos/ticket.css') }}">
</head>
<body class="ticket-pagina ticket-{{ $ancho }}" data-imprimir="{{ request('imprimir') ? '1' : '0' }}">
    <main class="impresion">
        <section class="impresora" id="impresora" data-fase="{{ $recienEmitido ? 'preparando' : 'reimpresion' }}">
            <div class="impresora__cuerpo">
                <header class="impresora__barra">
                    <span class="impresora__logo" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M6 3v6a2 2 0 0 0 1.5 1.94V21h1.2V10.94A2 2 0 0 0 10.2 9V3H9.1v5h-.6V3H7.7v5h-.6V3zM17.6 3c-2.2 1.2-3.2 3.6-3.2 6.4V14h2v7h1.2z" fill="currentColor"/></svg>
                    </span>

                    <nav class="impresora__botones" aria-label="Acciones del ticket">
                        <a href="{{ route('caja.ticket', ['numero' => $factura->numero_factura, 'ancho' => $ancho === 80 ? 58 : 80]) }}" class="pildora" title="Cambiar el ancho del papel">
                            <i class="ti ti-arrows-horizontal"></i> {{ $ancho === 80 ? '58' : '80' }} mm
                        </a>
                        <button type="button" class="pildora" id="imprimir-ticket">
                            <i class="ti ti-printer"></i> Imprimir
                        </button>
                        <a href="{{ route('caja.index') }}" class="pildora">
                            <i class="ti ti-home"></i> Caja
                        </a>
                    </nav>
                </header>

                <div class="impresora__pantalla">
                    <div class="impresora__resumen">
                        <div>
                            <p class="impresora__titulo">Factura {{ $factura->numero_factura }}</p>
                            <p class="impresora__detalle">Mesa {{ $factura->numero_mesa }} · {{ $factura->cajero }}</p>
                        </div>
                        <div class="impresora__total">
                            <span>Total</span>
                            <strong class="num">{{ $fmt($factura->total) }}</strong>
                        </div>
                    </div>

                    <p class="impresora__estado" role="status" aria-live="polite">
                        <span class="impresora__indicador" aria-hidden="true">
                            <span class="impresora__giro"></span>
                            <svg class="impresora__check" viewBox="0 0 20 20"><circle cx="10" cy="10" r="10"/><path d="M5.8 10.4l2.7 2.7 5.7-5.9"/></svg>
                        </span>
                        <span id="impresora-estado"
                            data-preparando="Preparando el ticket"
                            data-imprimiendo="Imprimiendo el ticket"
                            data-completo="Cobro completado"
                            data-reimpresion="Ticket listo para reimprimir">{{ $recienEmitido ? 'Preparando el ticket' : 'Ticket listo para reimprimir' }}</span>
                    </p>
                </div>

                <div class="impresora__ranura" aria-hidden="true"></div>
            </div>

            <div class="impresora__salida">
                <article class="ticket" id="ticket" aria-label="Ticket de la factura {{ $factura->numero_factura }}">
                    <div class="ticket__logo" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M6 3v6a2 2 0 0 0 1.5 1.94V21h1.2V10.94A2 2 0 0 0 10.2 9V3H9.1v5h-.6V3H7.7v5h-.6V3zM17.6 3c-2.2 1.2-3.2 3.6-3.2 6.4V14h2v7h1.2z" fill="currentColor"/></svg>
                    </div>
                    <p class="ticket__nombre">{{ mb_strtoupper(config('app.name')) }}</p>

                    <div class="ticket__linea"></div>

                    @foreach ($items as $i)
                        <div class="ticket__producto">
                            <div class="ticket__fila ticket__fila--fuerte">
                                <span>{{ $i->cantidad }} × {{ mb_strtoupper($i->nombre_producto) }}</span>
                                <span>{{ $fmt($i->cantidad * $i->precio_unitario) }}</span>
                            </div>
                            <div class="ticket__suave">{{ $fmt($i->precio_unitario) }} c/u</div>
                            @if ($i->notas)
                                <div class="ticket__nota">* {{ $i->notas }}</div>
                            @endif
                        </div>
                    @endforeach

                    <div class="ticket__linea"></div>

                    <div class="ticket__fila ticket__fila--suave"><span>Subtotal</span><span>{{ $fmt($factura->subtotal) }}</span></div>
                    <div class="ticket__fila ticket__fila--suave"><span>Descuento</span><span>-{{ $fmt($factura->descuento) }}</span></div>
                    <div class="ticket__fila ticket__fila--suave"><span>IVA (13%)</span><span>{{ $fmt($factura->impuesto_ventas) }}</span></div>
                    <div class="ticket__fila ticket__fila--suave"><span>Servicio (10%)</span><span>{{ $fmt($factura->servicio) }}</span></div>

                    <div class="ticket__fila ticket__total"><span>TOTAL PAGADO</span><span>{{ $fmt($factura->total) }}</span></div>

                    <div class="ticket__linea"></div>

                    <dl class="ticket__datos">
                        <dt>Factura</dt><dd>{{ $factura->numero_factura }}</dd>
                        <dt>Mesa</dt><dd>{{ $factura->numero_mesa }}</dd>
                        <dt>Cajero</dt><dd>{{ $factura->cajero }}</dd>
                        @foreach ($pagos as $p)
                            <dt>{{ $loop->first ? 'Pagado con' : '' }}</dt>
                            <dd>{{ $p->nombre_metodo }}@if ($p->referencia) · {{ $p->referencia }}@endif · {{ $fmt($p->monto) }}</dd>
                        @endforeach
                        <dt>Vuelto</dt><dd>{{ $fmt($factura->vuelto) }}</dd>
                        <dt>Fecha</dt><dd>{{ mb_strtoupper($fecha->locale('es')->translatedFormat('d M Y')) }} · {{ $fecha->format('H:i') }}</dd>
                    </dl>

                    <div class="ticket__codigo">
                        <svg viewBox="0 0 {{ $codigo['ancho'] }} 40" width="{{ $codigo['ancho'] * 1.5 }}" height="46" preserveAspectRatio="none" shape-rendering="crispEdges" role="img" aria-label="Código de barras {{ $textoCodigo }}">
                            @foreach ($codigo['barras'] as $barra)<rect x="{{ $barra['x'] }}" width="{{ $barra['ancho'] }}" height="40"/>@endforeach
                        </svg>
                        <span>{{ $textoCodigo }}</span>
                    </div>

                    <p class="ticket__gracias">¡Gracias por su visita!</p>
                </article>
            </div>
        </section>
    </main>

    <script src="{{ asset('js/sistema/app.js') }}"></script>
    <script src="{{ asset('js/modulos/ticket.js') }}"></script>
</body>
</html>
