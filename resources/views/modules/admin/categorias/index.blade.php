@extends('layouts.app')

@section('titulo', 'Categorías')

@section('botones')
    <x-admin.new-button :href="route('admin.categorias.create')" label="Nueva categoría" />
@endsection

@section('contenido')
    {{-- LISTADO DE CATEGORÍAS (tabla CATEGORIA) --}}
    <x-admin.table :headers="['#', 'Categoría', 'Descripción', 'Acciones']">
        @forelse ($items as $item)
            <tr class="border-t border-gray-100">
                <td class="px-4 py-3">{{ $item->id_categoria }}</td>
                <td class="px-4 py-3">{{ $item->nombre_categoria }}</td>
                <td class="px-4 py-3 text-gray-500">{{ $item->descripcion ?? '—' }}</td>
                <td class="px-4 py-3">
                    <x-admin.actions :edit="route('admin.categorias.edit', $item->id_categoria)" :destroy="route('admin.categorias.destroy', $item->id_categoria)" />
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">Sin registros</td></tr>
        @endforelse

        <x-slot:footer>{{ $items->links() }}</x-slot:footer>
    </x-admin.table>
@endsection
