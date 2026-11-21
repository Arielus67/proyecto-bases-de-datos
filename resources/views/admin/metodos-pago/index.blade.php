@extends('layouts.app')

@section('titulo', 'Métodos de pago')

@section('botones')
    <x-admin.new-button :href="route('admin.metodos-pago.create')" label="Nuevo método" />
@endsection

@section('contenido')
    {{-- LISTADO DE MÉTODOS DE PAGO (tabla METODO_PAGO) --}}
    <x-admin.table :headers="['#', 'Método', 'Acciones']">
        @forelse ($items as $item)
            <tr class="border-t border-gray-100">
                <td class="px-4 py-3">{{ $item->id_metodo_pago }}</td>
                <td class="px-4 py-3">{{ $item->nombre_metodo }}</td>
                <td class="px-4 py-3">
                    <x-admin.actions :edit="route('admin.metodos-pago.edit', $item->id_metodo_pago)" :destroy="route('admin.metodos-pago.destroy', $item->id_metodo_pago)" />
                </td>
            </tr>
        @empty
            <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">Sin registros</td></tr>
        @endforelse

        <x-slot:footer>{{ $items->links() }}</x-slot:footer>
    </x-admin.table>
@endsection
