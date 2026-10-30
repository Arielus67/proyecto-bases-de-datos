@extends('layouts.app')

@section('titulo', $item ? 'Editar receta' : 'Nueva receta')

@section('contenido')
    {{-- El controlador envía $productos (id => nombre), $insumos (id => "nombre (unidad)")
         y, al editar, $item = producto con ->recetas cargado. --}}
    @php
        // Líneas iniciales: las guardadas, las del old() tras un error, o una vacía
        $lineas = old('insumos')
            ? collect(old('insumos'))->map(fn ($id, $i) => ['id' => $id, 'cantidad' => old('cantidades')[$i] ?? ''])->values()
            : ($item ? $item->recetas->map(fn ($r) => ['id' => $r->id_insumo, 'cantidad' => $r->cantidad])->values() : collect([['id' => '', 'cantidad' => '']]));
    @endphp

    <x-admin.form :action="$item ? route('admin.recetas.update', $item->id_producto) : route('admin.recetas.store')"
                  :editing="(bool) $item" :cancel="route('admin.recetas.index')">

        {{-- ========== PRODUCTO (no se puede cambiar al editar) ========== --}}
        <div class="md:col-span-2">
            @if ($item)
                <input type="hidden" name="id_producto" value="{{ $item->id_producto }}">
                <p class="text-sm font-medium mb-1">Producto</p>
                <p class="rounded-md bg-gray-50 px-3 py-2 text-sm">{{ $item->nombre_producto }}</p>
            @else
                <x-admin.select name="id_producto" label="Producto" :options="$productos" required />
            @endif
        </div>

        {{-- ========== LÍNEAS DE INSUMOS (filas dinámicas) ========== --}}
        <div class="md:col-span-2">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-medium">Insumos y cantidades</p>
                <button type="button" id="agregar-linea" class="text-sm text-primary hover:underline">+ Agregar insumo</button>
            </div>

            <div id="lineas" class="space-y-3">
                @foreach ($lineas as $linea)
                    <div class="linea grid grid-cols-12 gap-3 items-center">
                        <select name="insumos[]" required class="col-span-7 rounded-md border border-gray-300 px-3 py-2 text-sm">
                            <option value="">Seleccione insumo...</option>
                            @foreach ($insumos as $id => $texto)
                                <option value="{{ $id }}" @selected((string) $linea['id'] === (string) $id)>{{ $texto }}</option>
                            @endforeach
                        </select>
                        <input type="number" name="cantidades[]" step="0.01" min="0.01" required placeholder="Cantidad"
                               value="{{ $linea['cantidad'] }}" class="col-span-4 rounded-md border border-gray-300 px-3 py-2 text-sm">
                        <button type="button" class="quitar col-span-1 text-gray-400 hover:text-red-600" title="Quitar"><i class="ti ti-x text-lg"></i></button>
                    </div>
                @endforeach
            </div>
            @error('insumos')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>

    </x-admin.form>
@endsection

{{-- SCRIPT: agregar y quitar filas de insumos --}}
@push('scripts')
<script>
    (function () {
        var cont = document.getElementById('lineas');

        // Agregar: clona la primera fila y la deja vacía
        document.getElementById('agregar-linea').addEventListener('click', function () {
            var nueva = cont.querySelector('.linea').cloneNode(true);
            nueva.querySelector('select').value = '';
            nueva.querySelector('input').value = '';
            cont.appendChild(nueva);
        });

        // Quitar: elimina la fila (siempre queda al menos una)
        cont.addEventListener('click', function (e) {
            var btn = e.target.closest('.quitar');
            if (btn && cont.querySelectorAll('.linea').length > 1) {
                btn.closest('.linea').remove();
            }
        });
    })();
</script>
@endpush
