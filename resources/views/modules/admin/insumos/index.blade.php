@extends('layouts.app')

@section('titulo', 'Insumos')

@section('botones')
    <x-admin.new-button :href="route('admin.insumos.create')" label="Nuevo insumo" />
@endsection

@section('contenido')
    {{-- LISTADO DE INSUMOS (tabla INSUMO) — resalta los que están en o bajo el stock mínimo --}}
    <x-admin.table :headers="['#', 'Insumo', 'Unidad', 'Stock actual', 'Stock mínimo', 'Costo unitario', 'Estado', 'Acciones']">
        @forelse ($items as $item)
            @php $bajo = $item->stock_actual <= $item->stock_minimo; @endphp
            <tr class="border-t border-gray-100">
                <td class="px-4 py-3">{{ $item->id_insumo }}</td>
                <td class="px-4 py-3 font-medium">{{ $item->nombre_insumo }}</td>
                <td class="px-4 py-3">{{ $item->unidad_medida }}</td>
                <td class="px-4 py-3">{{ number_format($item->stock_actual, 2) }}</td>
                <td class="px-4 py-3">{{ number_format($item->stock_minimo, 2) }}</td>
                <td class="px-4 py-3">₡{{ number_format($item->costo_unitario, 2) }}</td>
                <td class="px-4 py-3">
                    <x-admin.badge :color="$bajo ? 'red' : 'green'">{{ $bajo ? 'Stock bajo' : 'OK' }}</x-admin.badge>
                </td>
                <td class="px-4 py-3">
                    <x-admin.actions :edit="route('admin.insumos.edit', $item->id_insumo)" :destroy="route('admin.insumos.destroy', $item->id_insumo)" />
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="px-4 py-6 text-center text-gray-500">Sin registros</td></tr>
        @endforelse

        <x-slot:footer>{{ $items->links() }}</x-slot:footer>
    </x-admin.table>
@endsection
