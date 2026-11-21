@extends('layouts.app')

@section('titulo', $item ? 'Editar categoría' : 'Nueva categoría')

@section('contenido')
    <x-admin.form :action="$item ? route('admin.categorias.update', $item->id_categoria) : route('admin.categorias.store')"
                  :editing="(bool) $item" :cancel="route('admin.categorias.index')">

        {{-- CAMPOS: nombre_categoria, descripcion --}}
        <x-admin.input name="nombre_categoria" label="Nombre de la categoría" :value="$item->nombre_categoria ?? null" required maxlength="50" />
        <x-admin.input name="descripcion" label="Descripción" :value="$item->descripcion ?? null" maxlength="200" />

    </x-admin.form>
@endsection
