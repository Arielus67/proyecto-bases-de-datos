@extends('layouts.app')

@section('titulo', $item ? 'Editar empleado' : 'Nuevo empleado')

@section('contenido')
    {{-- El controlador envía $roles (id => nombre_rol) y $estaciones (id => nombre) --}}
    <x-admin.form :action="$item ? route('admin.empleados.update', $item->id_empleado) : route('admin.empleados.store')"
                  :editing="(bool) $item" :cancel="route('admin.empleados.index')">

        {{-- ========== DATOS GENERALES (tabla EMPLEADO) ========== --}}
        <x-admin.input name="nombre" label="Nombre" :value="$item->nombre ?? null" required maxlength="50" />
        <x-admin.input name="apellido" label="Apellido" :value="$item->apellido ?? null" required maxlength="50" />
        <x-admin.input name="correo" label="Correo" type="email" :value="$item->correo ?? null" required maxlength="100" />

        {{-- La contraseña solo es obligatoria al crear; el controlador la guarda en contrasena_hash con Hash::make() --}}
        <x-admin.input name="contrasena" label="Contraseña{{ $item ? ' (vacía = no cambiar)' : '' }}" type="password" :required="! $item" />

        <x-admin.select name="id_rol" label="Rol" :options="$roles" :value="$item->id_rol ?? null" required />
        <x-admin.checkbox name="activo" label="Empleado activo" :checked="$item->activo ?? true" />

        {{-- ========== SUBCLASE: MESERO ========== --}}
        <div id="bloque-mesero" class="md:col-span-2 hidden">
            <x-admin.input name="zona_asignada" label="Zona asignada" :value="$item->mesero->zona_asignada ?? null" maxlength="50" />
        </div>

        {{-- ========== SUBCLASE: COCINERO ========== --}}
        <div id="bloque-cocinero" class="md:col-span-2 hidden">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <x-admin.select name="id_estacion" label="Estación" :options="$estaciones" :value="$item->cocinero->id_estacion ?? null" />
                <x-admin.input name="especialidad" label="Especialidad" :value="$item->cocinero->especialidad ?? null" maxlength="50" />
            </div>
        </div>

        {{-- ========== SUBCLASE: CAJERO ========== --}}
        <div id="bloque-cajero" class="md:col-span-2 hidden">
            <x-admin.input name="codigo_caja" label="Código de caja" :value="$item->cajero->codigo_caja ?? null" maxlength="20" />
        </div>

    </x-admin.form>
@endsection

{{-- SCRIPT: muestra el bloque de la subclase según el nombre del rol elegido (Mesero / Cocinero / Cajero) --}}
@push('scripts')
<script>
    (function () {
        // Mapa id_rol => nombre en minúsculas, tomado de $roles
        var roles = @json(collect($roles)->map(fn ($n) => mb_strtolower($n)));
        var select = document.getElementById('id_rol');
        var bloques = {
            mesero:   document.getElementById('bloque-mesero'),
            cocinero: document.getElementById('bloque-cocinero'),
            cajero:   document.getElementById('bloque-cajero')
        };

        function actualizar() {
            var nombre = roles[select.value] || '';
            Object.keys(bloques).forEach(function (k) {
                bloques[k].classList.toggle('hidden', nombre.indexOf(k) === -1);
            });
        }
        select.addEventListener('change', actualizar);
        actualizar();
    })();
</script>
@endpush
