@extends('layouts.app')

@section('titulo', 'Reporte de ventas')

@section('contenido')
    <form method="GET" action="{{ route('admin.reportes.ventas') }}" class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 mb-6 flex flex-wrap items-end gap-4">
        <label class="text-sm">Desde
            <input type="date" name="fecha_inicio" value="{{ $inicio }}" class="block border rounded px-2 py-1">
        </label>
        <label class="text-sm">Hasta
            <input type="date" name="fecha_fin" value="{{ $fin }}" class="block border rounded px-2 py-1">
        </label>
        <label class="text-sm">Agrupar
            <select name="agrupar_por" class="block border rounded px-2 py-1">
                @foreach ($agrupaciones as $codigo => $etiqueta)
                    <option value="{{ $codigo }}" @selected($agrupar === $codigo)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit" class="px-4 py-2 rounded bg-primary text-white">Consultar</button>
    </form>

    @if ($error)
        <div class="mb-6 p-4 rounded border border-red-200 bg-red-50 text-red-700">No se pudo generar el reporte: {{ $error }}</div>
    @elseif ($filas->isEmpty())
        <p class="text-gray-500">No hay ventas cobradas en el período seleccionado.</p>
    @else
        @php
            $formatear = function ($valor, string $tipo) {
                return match ($tipo) {
                    'dinero'     => '₡' . number_format((float) $valor, 2),
                    'cantidad', 'entero' => number_format((float) $valor, 0),
                    'fecha_hora' => \Carbon\Carbon::parse($valor)->format('d/m/Y H:i'),
                    default      => $valor,
                };
            };
        @endphp

        <p class="mb-3 text-sm text-gray-500">
            {{ $filas->count() }} {{ $filas->count() === 1 ? 'fila' : 'filas' }} · {{ $agrupaciones[$agrupar] }}
            · del {{ \Carbon\Carbon::parse($inicio)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($fin)->format('d/m/Y') }}
        </p>

        <div class="overflow-x-auto bg-white border border-gray-200 rounded-lg shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left">
                    <tr>
                        @foreach ($columnas as $columna)
                            <th class="px-4 py-2 font-semibold text-gray-600 {{ $tipos[$columna] === 'texto' || $tipos[$columna] === 'fecha_hora' ? '' : 'text-right' }}">{{ str_replace('_', ' ', $columna) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($filas as $fila)
                        <tr class="border-t border-gray-100">
                            @foreach ($columnas as $columna)
                                <td class="px-4 py-2 {{ $tipos[$columna] === 'texto' || $tipos[$columna] === 'fecha_hora' ? '' : 'text-right num' }}">{{ $formatear($fila[$columna], $tipos[$columna]) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
                @if ($filas->count() > 1 && $totales)
                    <tfoot>
                        <tr class="border-t-2 border-gray-200 font-semibold">
                            @foreach ($columnas as $i => $columna)
                                <td class="px-4 py-2 {{ array_key_exists($columna, $totales) ? 'text-right num' : '' }}">
                                    @if (array_key_exists($columna, $totales))
                                        {{ $formatear($totales[$columna], $tipos[$columna]) }}
                                    @elseif ($i === 0)
                                        Total
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    @endif
@endsection
