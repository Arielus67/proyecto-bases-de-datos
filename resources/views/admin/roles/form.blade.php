@extends('layouts.app')

@section('titulo', $item ? 'Editar rol' : 'Nuevo rol')

@section('contenido')
    <x-admin.form :action="$item ? route('admin.roles.update', $item->id_rol) : route('admin.roles.store')"
                  :editing="(bool) $item" :cancel="route('admin.roles.index')">

        <x-admin.input name="nombre_rol" label="Nombre del rol" :value="$item->nombre_rol ?? null" required maxlength="50" />

        <div class="permisos-rol" id="permisos-rol">
            <div class="permisos-rol__cabecera">
                <p class="text-sm font-medium">Permisos del rol <span class="text-red-500">*</span></p>
                <label class="permisos-rol__casilla permisos-rol__casilla--suave">
                    <input type="checkbox" class="rounded" data-marcar-todo> Marcar todos
                </label>
            </div>
            @error('permisos')
                <p class="mb-2 text-xs text-red-500">{{ $message }}</p>
            @enderror

            @php($seleccion = old('permisos', $marcados))

            <div class="permisos-rol__lista">
                @foreach ($modulos as $modulo => $permisos)
                    <fieldset class="permisos-rol__modulo" data-modulo>
                        <legend class="sr-only">{{ $modulo }}</legend>
                        <label class="permisos-rol__casilla permisos-rol__casilla--modulo">
                            <input type="checkbox" class="rounded" data-marcar-modulo> {{ $modulo }}
                        </label>
                        <div class="permisos-rol__opciones">
                            @foreach ($permisos as $permiso)
                                <label class="permisos-rol__casilla" title="{{ $permiso->clave }}">
                                    <input type="checkbox" class="rounded" name="permisos[]" value="{{ $permiso->clave }}" @checked(in_array($permiso->clave, $seleccion, true))>
                                    {{ $permiso->descripcion }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>
        </div>
    </x-admin.form>
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('css/modulos/roles.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/modulos/roles.js') }}"></script>
@endpush
