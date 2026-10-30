@extends('layouts.app')

@section('titulo', 'Estaciones de cocina')

@section('botones')
    <x-admin.new-button :href="route('admin.estaciones.create')" label="Nueva estación" />
@endsection

@section('contenido')
    {{-- LISTADO DE ESTACIONES (tabla ESTACION) --}}
    <x-admin.table :headers="['#', 'Estación', 'Acciones']">
        @forelse ($items as $item)
            <tr class="border-t border-gray-100">
                <td class="px-4 py-3">{{ $item->id_estacion }}</td>
                <td class="px-4 py-3">{{ $item->nombre_estacion }}</td>
                <td class="px-4 py-3">
                    <x-admin.actions :edit="route('admin.estaciones.edit', $item->id_estacion)" :destroy="route('admin.estaciones.destroy', $item->id_estacion)" />
                </td>
            </tr>
        @empty
            <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">Sin registros</td></tr>
        @endforelse

        <x-slot:footer>{{ $items->links() }}</x-slot:footer>
    </x-admin.table>
@endsection
