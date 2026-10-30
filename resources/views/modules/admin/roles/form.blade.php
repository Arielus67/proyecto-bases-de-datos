@extends('layouts.app')

{{-- Este formulario sirve para crear ($item = null) y editar ($item = modelo) --}}
@section('titulo', $item ? 'Editar rol' : 'Nuevo rol')

@section('contenido')
    <x-admin.form :action="$item ? route('admin.roles.update', $item->id_rol) : route('admin.roles.store')"
                  :editing="(bool) $item" :cancel="route('admin.roles.index')">

        {{-- CAMPOS: nombre_rol --}}
        <x-admin.input name="nombre_rol" label="Nombre del rol" :value="$item->nombre_rol ?? null" required maxlength="50" />

    </x-admin.form>
@endsection
