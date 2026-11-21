@extends('layouts.app')

@section('titulo', 'Turnos de caja')

@section('botones')
    <x-admin.new-button :href="route('admin.turnos.create')" label="Abrir turno" />
@endsection

@section('contenido')
    {{-- LISTADO DE TURNOS (tabla TURNO_CAJA). Un turno sin fecha_cierre está abierto. --}}
    <x-admin.table :headers="['#', 'Cajero', 'Apertura', 'Cierre', 'Monto inicial', 'Monto final', 'Estado', 'Acciones']">
        @forelse ($items as $item)
            <tr class="border-t border-gray-100">
                <td class="px-4 py-3">{{ $item->id_turno }}</td>
                <td class="px-4 py-3">{{ $item->empleado->nombre ?? '—' }} {{ $item->empleado->apellido ?? '' }}</td>
                <td class="px-4 py-3">{{ \Carbon\Carbon::parse($item->fecha_apertura)->format('d/m/Y H:i') }}</td>
                <td class="px-4 py-3">{{ $item->fecha_cierre ? \Carbon\Carbon::parse($item->fecha_cierre)->format('d/m/Y H:i') : '—' }}</td>
                <td class="px-4 py-3">₡{{ number_format($item->monto_inicial, 2) }}</td>
                <td class="px-4 py-3">{{ $item->monto_final !== null ? '₡' . number_format($item->monto_final, 2) : '—' }}</td>
                <td class="px-4 py-3"><x-admin.badge :color="$item->fecha_cierre ? 'gray' : 'green'">{{ $item->fecha_cierre ? 'Cerrado' : 'Abierto' }}</x-admin.badge></td>
                <td class="px-4 py-3"><x-admin.actions :edit="route('admin.turnos.edit', $item->id_turno)" /></td>
            </tr>
        @empty
            <tr><td colspan="8" class="px-4 py-6 text-center text-gray-500">Sin registros</td></tr>
        @endforelse

        <x-slot:footer>{{ $items->links() }}</x-slot:footer>
    </x-admin.table>
@endsection
