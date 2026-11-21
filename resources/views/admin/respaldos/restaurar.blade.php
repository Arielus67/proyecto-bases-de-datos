@extends('layouts.app')

@section('titulo', 'Restaurar la base de datos')

@section('contenido')
    <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <p class="font-semibold">Esta acción reemplaza todos los datos actuales de SQL Server por los del respaldo elegido.</p>
        <ul class="mt-2 list-disc pl-5 space-y-1">
            <li>Antes de restaurar se genera automáticamente un respaldo del estado actual, para poder volver atrás.</li>
            <li>Todos los usuarios conectados quedan fuera y deben volver a iniciar sesión.</li>
            <li>Los pedidos, facturas y cambios hechos después de la fecha del respaldo se pierden.</li>
            <li>Solo se restaura SQL Server; las comandas y la bitácora de MongoDB no cambian.</li>
        </ul>
    </div>

    <form method="POST" action="{{ route('admin.respaldos.restaurar.ejecutar') }}" class="bg-white border border-gray-200 rounded-lg shadow-sm p-6" data-confirm="¿Restaurar la base de datos ahora? Los datos actuales se reemplazarán.">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="md:col-span-2">
                <x-admin.select
                    name="id_respaldo"
                    label="Respaldo que se va a restaurar"
                    :options="$respaldos->mapWithKeys(fn ($respaldo) => [$respaldo->id_respaldo => \Carbon\Carbon::parse($respaldo->fecha_hora)->format('d/m/Y H:i:s') . ' · ' . $respaldo->nombre_archivo])->all()"
                    :value="$elegido"
                    placeholder="Seleccione un respaldo verificado..."
                    required
                />
            </div>

            <x-admin.input name="contrasena" label="Tu contraseña de administrador" type="password" required autocomplete="current-password" />

            <div class="flex items-end">
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="confirmo" value="1" class="rounded mt-0.5" required @checked(old('confirmo'))>
                    <span>Entiendo que los datos actuales se reemplazan por los del respaldo.</span>
                </label>
            </div>
            @error('confirmo')
                <p class="md:col-span-2 -mt-3 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
            <a href="{{ route('admin.respaldos.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">Cancelar</a>
            <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                <i class="ti ti-database-import"></i> Restaurar base de datos
            </button>
        </div>
    </form>
@endsection
