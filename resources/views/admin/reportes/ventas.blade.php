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
        <div class="overflow-x-auto bg-white border border-gray-200 rounded-lg shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left">
                    <tr>
                        @foreach (array_keys($filas->first()) as $columna)
                            <th class="px-4 py-2 font-semibold text-gray-600">{{ str_replace('_', ' ', $columna) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($filas as $fila)
                        <tr class="border-t border-gray-100">
                            @foreach ($fila as $valor)
                                <td class="px-4 py-2">{{ is_numeric($valor) ? number_format((float) $valor, 2) : $valor }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
