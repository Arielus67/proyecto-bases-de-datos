@extends('layouts.app')

@section('titulo', $item ? 'Editar método de pago' : 'Nuevo método de pago')

@section('contenido')
    <x-admin.form :action="$item ? route('admin.metodos-pago.update', $item->id_metodo_pago) : route('admin.metodos-pago.store')"
                  :editing="(bool) $item" :cancel="route('admin.metodos-pago.index')">

        {{-- CAMPOS: nombre_metodo --}}
        <x-admin.input name="nombre_metodo" label="Nombre del método" :value="$item->nombre_metodo ?? null" required maxlength="50" />

    </x-admin.form>
@endsection
