@extends('layouts.app')

@section('titulo', $item ? 'Cerrar / editar turno #' . $item->id_turno : 'Abrir turno de caja')

@section('contenido')
    {{-- El controlador envía $cajeros (id_empleado => nombre completo, solo empleados con rol cajero) --}}
    <x-admin.form :action="$item ? route('admin.turnos.update', $item->id_turno) : route('admin.turnos.store')"
                  :editing="(bool) $item" :cancel="route('admin.turnos.index')">

        {{-- ========== APERTURA: id_empleado, monto_inicial ========== --}}
        <x-admin.select name="id_empleado" label="Cajero" :options="$cajeros" :value="$item->id_empleado ?? null" required />
        <x-admin.input name="monto_inicial" label="Monto inicial" type="number" step="0.01" min="0" :value="$item->monto_inicial ?? null" required />

        {{-- ========== CIERRE (solo al editar): fecha_cierre, monto_final ========== --}}
        @if ($item)
            <x-admin.input name="fecha_cierre" label="Fecha de cierre" type="datetime-local"
                           :value="$item->fecha_cierre ? \Carbon\Carbon::parse($item->fecha_cierre)->format('Y-m-d\TH:i') : null" />
            <x-admin.input name="monto_final" label="Monto final" type="number" step="0.01" min="0" :value="$item->monto_final ?? null" />
        @endif

    </x-admin.form>
@endsection
