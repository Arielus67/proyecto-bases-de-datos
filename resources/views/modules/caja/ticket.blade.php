@php
    // Ancho del papel: 80 mm por defecto. Para impresoras de 58 mm usa ?ancho=58
    $ancho  = request('ancho') === '58' ? 58 : 80;
    $fuente = $ancho === 58 ? '10px' : '12px';

    // Se usa "CRC" porque muchas impresoras térmicas no imprimen el símbolo ₡
    $fmt = fn ($v) => 'CRC ' . number_format((float) $v, 2, ',', ' ');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket {{ $factura->numero_factura }}</title>

    <style>
        /* Tamaño de página para impresora térmica: ancho fijo, largo automático */
        @page {
            size: {{ $ancho }}mm auto;
            margin: 0;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0;
            background: #e5e7eb;
            font-family: 'Courier New', Courier, monospace;
            font-size: {{ $fuente }};
            color: #000;
        }

        /* Barra de acciones (solo en pantalla) */
        .acciones {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 16px;
            font-family: sans-serif;
        }

        .acciones a,
        .acciones button {
            padding: 9px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid #d1d5db;
            background: #fff;
            color: #111827;
        }

        .acciones .principal {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
        }

        /* El ticket */
        .ticket {
            width: {{ $ancho }}mm;
            margin: 0 auto 24px;
            padding: 4mm 3mm;
            background: #fff;
            box-shadow: 0 2px 8px rgb(0 0 0 / 15%);
            line-height: 1.35;
        }

        .centro { text-align: center; }
        .negrita { font-weight: bold; }

        .titulo {
            font-size: {{ $ancho === 58 ? '14px' : '16px' }};
            font-weight: bold;
            margin: 0;
        }

        .linea {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }

        .fila {
            display: flex;
            justify-content: space-between;
            gap: 6px;
        }

        .fila span:last-child {
            text-align: right;
            white-space: nowrap;
        }

        .producto { margin-bottom: 4px; }

        .producto .nombre {
            font-weight: bold;
            word-break: break-word;
        }

        .nota {
            font-size: 0.9em;
            padding-left: 8px;
        }

        .total {
            font-size: 1.2em;
            font-weight: bold;
        }

        /* Impresión: solo el ticket, sin fondo, sombra ni botones */
        @media print {
            body { background: #fff; }
            .acciones { display: none !important; }

            .ticket {
                width: 100%;
                margin: 0;
                padding: 2mm 2mm;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>

    <div class="acciones">
        <a href="{{ route('caja.index') }}">← Volver a Caja</a>
        <button type="button" class="principal" onclick="window.print()">Imprimir ticket</button>
        <a href="{{ route('caja.ticket', ['numero' => $factura->numero_factura, 'ancho' => $ancho === 80 ? 58 : 80]) }}">
            Ver en {{ $ancho === 80 ? '58' : '80' }} mm
        </a>
    </div>

    <div class="ticket">

        {{-- CABECERA --}}
        <div class="centro">
            <p class="titulo">RESTAURANTE</p>
            <div>Factura: {{ $factura->numero_factura }}</div>
            <div>{{ \Carbon\Carbon::parse($factura->fecha_hora)->format('d/m/Y H:i') }}</div>
            <div>Mesa {{ $factura->numero_mesa }}</div>
            <div>Cajero: {{ $factura->cajero }}</div>
        </div>

        <div class="linea"></div>

        {{-- PRODUCTOS --}}
        @foreach ($items as $i)
            <div class="producto">
                <div class="nombre">{{ $i->nombre_producto }}</div>
                <div class="fila">
                    <span>{{ $i->cantidad }} x {{ number_format((float) $i->precio_unitario, 2, ',', ' ') }}</span>
                    <span>{{ number_format($i->cantidad * $i->precio_unitario, 2, ',', ' ') }}</span>
                </div>
                @if ($i->notas)
                    <div class="nota">* {{ $i->notas }}</div>
                @endif
            </div>
        @endforeach

        <div class="linea"></div>

        {{-- TOTALES --}}
        <div class="fila"><span>Subtotal:</span><span>{{ $fmt($factura->subtotal) }}</span></div>
        <div class="fila"><span>Descuento:</span><span>-{{ $fmt($factura->descuento) }}</span></div>
        <div class="fila"><span>IVA (13%):</span><span>{{ $fmt($factura->impuesto_ventas) }}</span></div>
        <div class="fila"><span>Servicio (10%):</span><span>{{ $fmt($factura->servicio) }}</span></div>

        <div class="linea"></div>

        <div class="fila total"><span>TOTAL:</span><span>{{ $fmt($factura->total) }}</span></div>

        <div class="linea"></div>

        {{-- PAGOS --}}
        @foreach ($pagos as $p)
            <div class="fila">
                <span>{{ $p->nombre_metodo }}@if ($p->referencia) ({{ $p->referencia }})@endif</span>
                <span>{{ $fmt($p->monto) }}</span>
            </div>
        @endforeach
        <div class="fila negrita"><span>Vuelto:</span><span>{{ $fmt($factura->vuelto) }}</span></div>

        <div class="linea"></div>

        <div class="centro">
            ¡Gracias por su visita!
        </div>
    </div>

    @if (request('imprimir'))
        <script>window.addEventListener('load', () => window.print());</script>
    @endif

</body>
</html>
