@extends('layouts.app')

@section('titulo', $item ? 'Editar insumo' : 'Nuevo insumo')

@section('contenido')
    <x-admin.form :action="$item ? route('admin.insumos.update', $item->id_insumo) : route('admin.insumos.store')"
                  :editing="(bool) $item" :cancel="route('admin.insumos.index')">

        {{-- CAMPOS: nombre_insumo, unidad_medida, stock_actual, stock_minimo, costo_unitario --}}
        <x-admin.input name="nombre_insumo" label="Nombre del insumo" :value="$item->nombre_insumo ?? null" required maxlength="100" />
        <x-admin.input name="unidad_medida" label="Unidad de medida (kg, L, unidad...)" :value="$item->unidad_medida ?? null" required maxlength="20" />
        <x-admin.input name="stock_actual" label="Stock actual" type="number" step="0.01" min="0" :value="$item->stock_actual ?? null" required />
        <x-admin.input name="stock_minimo" label="Stock mínimo" type="number" step="0.01" min="0" :value="$item->stock_minimo ?? null" required />
        <x-admin.input name="costo_unitario" label="Costo unitario" type="number" step="0.01" min="0" :value="$item->costo_unitario ?? null" required />

    </x-admin.form>
@endsection
