@extends('layouts.app')

@section('titulo', $item ? 'Editar producto' : 'Nuevo producto')

@section('contenido')
    {{-- El controlador envía $categorias (id => nombre) y $estaciones (id => nombre) --}}
    <x-admin.form :action="$item ? route('admin.productos.update', $item->id_producto) : route('admin.productos.store')"
                  :editing="(bool) $item" :cancel="route('admin.productos.index')">

        {{-- CAMPOS: nombre_producto, descripcion, precio, id_categoria, id_estacion, activo --}}
        <x-admin.input name="nombre_producto" label="Nombre del producto" :value="$item->nombre_producto ?? null" required maxlength="100" />
        <x-admin.input name="descripcion" label="Descripción" :value="$item->descripcion ?? null" maxlength="255" />
        <x-admin.input name="precio" label="Precio" type="number" step="0.01" min="0" :value="$item->precio ?? null" required />
        <x-admin.select name="id_categoria" label="Categoría" :options="$categorias" :value="$item->id_categoria ?? null" required />
        <x-admin.select name="id_estacion" label="Estación de cocina" :options="$estaciones" :value="$item->id_estacion ?? null" required />
        <x-admin.checkbox name="activo" label="Producto activo" :checked="$item->activo ?? true" />

    </x-admin.form>
@endsection
