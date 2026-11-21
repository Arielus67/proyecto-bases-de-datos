@extends('layouts.app')

@section('titulo', $item ? 'Editar producto' : 'Nuevo producto')

@section('contenido')
    {{-- El controlador envía $categorias (id => nombre) y $estaciones (id => nombre) --}}
    <x-admin.form :action="$item ? route('admin.productos.update', $item->id_producto) : route('admin.productos.store')"
                  :editing="(bool) $item" :cancel="route('admin.productos.index')" archivos>

        {{-- CAMPOS: nombre_producto, descripcion, precio, id_categoria, id_estacion, activo --}}
        <x-admin.input name="nombre_producto" label="Nombre del producto" :value="$item->nombre_producto ?? null" required maxlength="100" />
        <x-admin.input name="descripcion" label="Descripción" :value="$item->descripcion ?? null" maxlength="255" />
        <x-admin.input name="precio" label="Precio" type="number" step="0.01" min="0" :value="$item->precio ?? null" required />
        <x-admin.select name="id_categoria" label="Categoría" :options="$categorias" :value="$item->id_categoria ?? null" required />
        <x-admin.select name="id_estacion" label="Estación de cocina" :options="$estaciones" :value="$item->id_estacion ?? null" required />
        <x-admin.checkbox name="activo" label="Producto activo" :checked="$item->activo ?? true" />

        @php
            $urlImagen = $item?->url_imagen;
            $imagenSubida = $item && $item->foto;
        @endphp
        <div class="campo campo-imagen md:col-span-2">
            <span class="campo__etiqueta">Imagen para el menú</span>

            <div class="campo-imagen__cuerpo">
                <div class="campo-imagen__vista {{ $urlImagen ? '' : 'campo-imagen__vista--vacia' }}" id="imagen-vista">
                    <img src="{{ $urlImagen }}" alt="Vista previa de la imagen" id="imagen-previa" @if (! $urlImagen) hidden @endif>
                    <span class="campo-imagen__marcador" @if ($urlImagen) hidden @endif><i class="ti ti-photo"></i> Sin imagen</span>
                </div>

                <div class="campo-imagen__acciones">
                    <label class="btn btn--secundario" for="imagen"><i class="ti ti-upload"></i> Elegir imagen</label>
                    <input type="file" name="imagen" id="imagen" class="campo-imagen__archivo" accept="image/jpeg,image/png,image/webp">
                    <p class="campo-imagen__ayuda">JPG, PNG o WEBP · máximo 2 MB · se recomienda 800×600 px (4:3).</p>
                    <p class="campo-imagen__nombre" id="imagen-nombre" hidden></p>

                    @if ($imagenSubida)
                        <label class="campo-imagen__quitar">
                            <input type="checkbox" name="quitar_imagen" value="1" id="quitar-imagen">
                            Quitar la imagen
                        </label>
                    @endif

                    @error('imagen')
                        <p class="campo__error">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

    </x-admin.form>
@endsection

@push('scripts')
    <script src="{{ asset('js/modulos/productos.js') }}"></script>
@endpush
