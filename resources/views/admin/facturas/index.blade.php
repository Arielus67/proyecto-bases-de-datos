@extends('layouts.app')

@section('titulo', 'Facturas')

@section('contenido')
    {{-- ========== FILTROS (estado y fecha) ========== --}}
    <form method="GET" class="flex flex-wrap items-end gap-3 mb-5">
        <div>
            <label class="block text-xs font-medium mb-1">Estado</label>
            <select name="estado" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
                <option value="">Todos</option>
                <option value="COBRADA" @selected(request('estado') === 'COBRADA')>COBRADA</option>
                <option value="ANULADA" @selected(request('estado') === 'ANULADA')>ANULADA</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium mb-1">Fecha</label>
            <input type="date" name="fecha" value="{{ request('fecha') }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>
        <button class="rounded-md bg-primary px-4 py-2 text-sm text-white">Filtrar</button>
        <a href="{{ route('admin.facturas.index') }}" class="px-3 py-2 text-sm text-gray-500 hover:underline">Limpiar</a>
    </form>

    {{-- ========== LISTADO DE FACTURAS (tabla FACTURA) ========== --}}
    <x-admin.table :headers="['N° factura', 'Pedido', 'Cliente', 'Cajero', 'Fecha', 'Total', 'Estado', 'Acciones']">
        @forelse ($items as $item)
            <tr class="border-t border-gray-100">
                <td class="px-4 py-3 font-medium">{{ $item->numero_factura }}</td>
                <td class="px-4 py-3">#{{ $item->id_pedido }}</td>
                <td class="px-4 py-3">{{ $item->cliente ? $item->cliente->nombre . ' ' . $item->cliente->apellido : 'Consumidor final' }}</td>
                <td class="px-4 py-3">{{ $item->cajero->nombre ?? '—' }}</td>
                <td class="px-4 py-3">{{ \Carbon\Carbon::parse($item->fecha_hora)->format('d/m/Y H:i') }}</td>
                <td class="px-4 py-3">₡{{ number_format($item->total, 2) }}</td>
                <td class="px-4 py-3"><x-admin.badge :color="$item->estado === 'COBRADA' ? 'green' : 'red'">{{ $item->estado }}</x-admin.badge></td>
                <td class="px-4 py-3"><x-admin.actions :show="route('admin.facturas.show', $item->id_factura)" /></td>
            </tr>
        @empty
            <tr><td colspan="8" class="px-4 py-6 text-center text-gray-500">Sin registros</td></tr>
        @endforelse

        <x-slot:footer>{{ $items->withQueryString()->links() }}</x-slot:footer>
    </x-admin.table>
@endsection
