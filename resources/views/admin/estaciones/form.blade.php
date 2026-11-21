@extends('layouts.app')

@section('titulo', $item ? 'Editar estación' : 'Nueva estación')

@section('contenido')
    <x-admin.form :action="$item ? route('admin.estaciones.update', $item->id_estacion) : route('admin.estaciones.store')"
                  :editing="(bool) $item" :cancel="route('admin.estaciones.index')">

        {{-- CAMPOS: nombre_estacion --}}
        <x-admin.input name="nombre_estacion" label="Nombre de la estación" :value="$item->nombre_estacion ?? null" required maxlength="50" />

    </x-admin.form>
@endsection
