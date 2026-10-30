@extends('layouts.app')

@section('titulo', $item ? 'Editar mesa' : 'Nueva mesa')

@section('contenido')
    <x-admin.form :action="$item ? route('admin.mesas.update', $item->id_mesa) : route('admin.mesas.store')"
                  :editing="(bool) $item" :cancel="route('admin.mesas.index')">

        {{-- CAMPOS: numero_mesa, capacidad, zona, estado --}}
        <x-admin.input name="numero_mesa" label="Número de mesa" type="number" min="1" :value="$item->numero_mesa ?? null" required />
        <x-admin.input name="capacidad" label="Capacidad (personas)" type="number" min="1" :value="$item->capacidad ?? null" required />
        <x-admin.input name="zona" label="Zona" :value="$item->zona ?? null" required maxlength="50" />
        <x-admin.select name="estado" label="Estado" required :value="$item->estado ?? 'DISPONIBLE'"
            :options="['DISPONIBLE' => 'Disponible', 'OCUPADA' => 'Ocupada', 'RESERVADA' => 'Reservada', 'MANTENIMIENTO' => 'Mantenimiento']" />

    </x-admin.form>
@endsection
