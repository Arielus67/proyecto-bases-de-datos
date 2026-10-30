@extends('layouts.app')

@section('titulo', 'Roles')

{{-- BOTÓN SUPERIOR --}}
@section('botones')
    <x-admin.new-button :href="route('admin.roles.create')" label="Nuevo rol" />
@endsection

@section('contenido')
    {{-- LISTADO DE ROLES (tabla ROL) --}}
    <x-admin.table :headers="['#', 'Nombre del rol', 'Acciones']">
        @forelse ($items as $item)
            <tr class="border-t border-gray-100">
                <td class="px-4 py-3">{{ $item->id_rol }}</td>
                <td class="px-4 py-3">{{ $item->nombre_rol }}</td>
                <td class="px-4 py-3">
                    <x-admin.actions :edit="route('admin.roles.edit', $item->id_rol)" :destroy="route('admin.roles.destroy', $item->id_rol)" />
                </td>
            </tr>
        @empty
            <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">Sin registros</td></tr>
        @endforelse

        <x-slot:footer>{{ $items->links() }}</x-slot:footer>
    </x-admin.table>
@endsection
