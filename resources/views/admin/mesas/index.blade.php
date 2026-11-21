@extends('layouts.app')

@section('titulo', 'Mesas')

@section('botones')
    <x-admin.new-button :href="route('admin.mesas.create')" label="Nueva mesa" />
@endsection

@section('contenido')
    {{-- COLORES POR ESTADO DE MESA --}}
    @php
        $colores = ['DISPONIBLE' => 'green', 'OCUPADA' => 'red', 'RESERVADA' => 'yellow', 'MANTENIMIENTO' => 'gray'];
    @endphp

    {{-- LISTADO DE MESAS (tabla MESA) --}}
    <x-admin.table :headers="['#', 'Número', 'Capacidad', 'Zona', 'Estado', 'Acciones']">
        @forelse ($items as $item)
            <tr class="border-t border-gray-100">
                <td class="px-4 py-3">{{ $item->id_mesa }}</td>
                <td class="px-4 py-3 font-medium">Mesa {{ $item->numero_mesa }}</td>
                <td class="px-4 py-3">{{ $item->capacidad }} personas</td>
                <td class="px-4 py-3">{{ $item->zona }}</td>
                <td class="px-4 py-3"><x-admin.badge :color="$colores[$item->estado] ?? 'gray'">{{ $item->estado }}</x-admin.badge></td>
                <td class="px-4 py-3">
                    <x-admin.actions :edit="route('admin.mesas.edit', $item->id_mesa)" :destroy="route('admin.mesas.destroy', $item->id_mesa)" />
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">Sin registros</td></tr>
        @endforelse

        <x-slot:footer>{{ $items->links() }}</x-slot:footer>
    </x-admin.table>
@endsection
