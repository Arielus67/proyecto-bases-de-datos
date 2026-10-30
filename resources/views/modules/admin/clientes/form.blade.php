@extends('layouts.app')

@section('titulo', $item ? 'Editar cliente' : 'Nuevo cliente')

@section('contenido')
    <x-admin.form :action="$item ? route('admin.clientes.update', $item->id_cliente) : route('admin.clientes.store')"
                  :editing="(bool) $item" :cancel="route('admin.clientes.index')">

        {{-- CAMPOS: cedula (se cifra en el controlador con AES-256), nombre, apellido, teléfono, correo --}}
        {{-- Al editar, la cédula se deja vacía para conservar la actual; el controlador solo la cifra si viene con valor. --}}
        <x-admin.input name="cedula" label="Cédula" :required="! $item" maxlength="20"
                       placeholder="{{ $item ? 'Dejar vacío para no cambiarla' : '' }}" :value="null" />
        <x-admin.input name="nombre" label="Nombre" :value="$item->nombre ?? null" required maxlength="50" />
        <x-admin.input name="apellido" label="Apellido" :value="$item->apellido ?? null" required maxlength="50" />
        <x-admin.input name="telefono" label="Teléfono" :value="$item->telefono ?? null" maxlength="20" />
        <x-admin.input name="correo" label="Correo" type="email" :value="$item->correo ?? null" maxlength="100" />

    </x-admin.form>
@endsection
