@extends('layouts.app')

@section('titulo', 'Pedidos')

@section('contenido')
    {{-- COLORES POR ESTADO DE PEDIDO --}}
    @php
        $colores = ['ABIERTO' => 'blue', 'EN_PREPARACION' => 'yellow', 'LISTO' => 'green', 'ENTREGADO' => 'green', 'FACTURADO' => 'gray', 'ANULADO' => 'red'];
    @endphp

    {{-- ========== FILTROS (estado y fecha) ========== --}}
    <form method="GET" class="flex flex-wrap items-end gap-3 mb-5">
        <div>
            <label class="block text-xs font-medium mb-1">Estado</label>
            <select name="estado" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
                <option value="">Todos</option>
                @foreach (array_keys($colores) as $estado)
                    <option value="{{ $estado }}" @selected(request('estado') === $estado)>{{ $estado }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium mb-1">Fecha</label>
            <input type="date" name="fecha" value="{{ request('fecha') }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>
        <button class="rounded-md bg-primary px-4 py-2 text-sm text-white">Filtrar</button>
        <a href="{{ route('admin.pedidos.index') }}" class="px-3 py-2 text-sm text-gray-500 hover:underline">Limpiar</a>
    </form>

    {{-- ========== LISTADO DE PEDIDOS (tabla PEDIDO) ========== --}}
    <x-admin.table :headers="['#', 'Mesa', 'Mesero', 'Fecha', 'Subtotal', 'Estado', 'Acciones']">
        @forelse ($items as $item)
            <tr class="border-t border-gray-100">
                <td class="px-4 py-3">#{{ $item->id_pedido }}</td>
                <td class="px-4 py-3">Mesa {{ $item->mesa->numero_mesa ?? '—' }}</td>
                <td class="px-4 py-3">{{ $item->empleado->nombre ?? '—' }} {{ $item->empleado->apellido ?? '' }}</td>
                <td class="px-4 py-3">{{ \Carbon\Carbon::parse($item->fecha_hora)->format('d/m/Y H:i') }}</td>
                <td class="px-4 py-3">₡{{ number_format($item->subtotal, 2) }}</td>
                <td class="px-4 py-3"><x-admin.badge :color="$colores[$item->estado] ?? 'gray'">{{ $item->estado }}</x-admin.badge></td>
                <td class="px-4 py-3"><x-admin.actions :show="route('admin.pedidos.show', $item->id_pedido)" /></td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">Sin registros</td></tr>
        @endforelse

        <x-slot:footer>{{ $items->withQueryString()->links() }}</x-slot:footer>
    </x-admin.table>
@endsection
