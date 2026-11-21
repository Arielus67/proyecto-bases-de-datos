@extends('layouts.app')

@section('titulo', 'Clientes')

@section('botones')
    <x-admin.new-button :href="route('admin.clientes.create')" label="Nuevo cliente" />
@endsection

@section('contenido')
    {{-- LISTADO DE CLIENTES (tabla CLIENTE) --}}
    <x-admin.table :headers="['#', 'Cédula', 'Nombre', 'Teléfono', 'Correo', 'Acciones']">
        @forelse ($items as $item)
            <tr class="border-t border-gray-100">
                <td class="px-4 py-3">{{ $item->id_cliente }}</td>
                <td class="px-4 py-3">{{ $item->cedula }}</td>
                <td class="px-4 py-3">{{ $item->nombre }} {{ $item->apellido }}</td>
                <td class="px-4 py-3">{{ $item->telefono ?? '—' }}</td>
                <td class="px-4 py-3">{{ $item->correo ?? '—' }}</td>
                <td class="px-4 py-3">
                    <x-admin.actions :edit="route('admin.clientes.edit', $item->id_cliente)" :destroy="route('admin.clientes.destroy', $item->id_cliente)" />
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">Sin registros</td></tr>
        @endforelse

        <x-slot:footer>{{ $items->links() }}</x-slot:footer>
    </x-admin.table>
@endsection
