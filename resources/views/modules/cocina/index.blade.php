@extends('app')

@section('titulo', 'KDS - Pantalla de Cocina')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/cocina.css') }}?v={{ time() }}">
@endpush

@section('contenido')

{{-- ENCABEZADO DEL MÓDULO --}}
<div class="kds-top">
    <div class="kds-top-info">
        <div class="kds-top-icono">
            <i class="ti ti-tools-kitchen-2"></i>
        </div>
        <div>
            <h2 class="kds-titulo">Kitchen Display System (KDS)</h2>
            <p class="kds-subtitulo">Gestión de comandas en tiempo real desde MongoDB</p>
        </div>
    </div>

    <button type="button" onclick="window.location.reload()" class="kds-btn-actualizar">
        <i class="ti ti-rotate-clockwise"></i>
        Actualizar Pantalla
    </button>
</div>

{{-- CUADRÍCULA DE COMANDAS --}}
<div class="kds-grid">
    @forelse($comandas as $comanda)
        @php
            $esAbierto = $comanda->estado === 'ABIERTO';
            $claseCard = $esAbierto ? 'estado-abierto' : 'estado-preparacion';
            $claseBadge = $esAbierto ? 'badge-abierto' : 'badge-preparacion';
        @endphp

        <div class="kds-card {{ $claseCard }}">

            {{-- Cabecera --}}
            <div class="kds-card-header">
                <div class="kds-header-fila">
                    <div class="kds-mesa-info">
                        <i class="ti ti-armchair"></i>
                        <div>
                            <span class="kds-etiqueta">Mesa</span>
                            <h3 class="kds-mesa-nombre">Mesa {{ $comanda->id_mesa }}</h3>
                        </div>
                    </div>

                    <div class="kds-pedido">
                        <span class="kds-etiqueta">Pedido SQL</span>
                        <span class="kds-pedido-num">#{{ $comanda->id_pedido_sql }}</span>
                    </div>
                </div>

                <div class="kds-header-pie">
                    <span class="kds-badge {{ $claseBadge }}">
                        {{ str_replace('_', ' ', $comanda->estado) }}
                    </span>
                    <span class="kds-hora">
                        <i class="ti ti-clock"></i> {{ $comanda->created_at->format('H:i:s') }}
                    </span>
                </div>
            </div>

            {{-- Cuerpo --}}
            <div class="kds-card-body">
                <h4 class="kds-body-titulo">Productos solicitados</h4>

                <ul class="kds-lista">
                    @foreach($comanda->detalles_con_modificadores as $detalle)
                        <li class="kds-item">
                            <div class="kds-item-fila">
                                <span>{{ $nombresProductos[$detalle['id_producto']] ?? 'Producto #' . $detalle['id_producto'] }}</span>
                                <span class="kds-cantidad">x{{ $detalle['cantidad'] }}</span>
                            </div>

                            @if(!empty($detalle['notas']))
                                <p class="kds-nota">
                                    <i class="ti ti-alert-circle"></i> Nota: {{ $detalle['notas'] }}
                                </p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Acción --}}
            <div class="kds-card-footer">
                @if($comanda->estado === 'ABIERTO')
                    <button type="button"
                        onclick="cambiarEstado(this, '{{ $comanda->_id }}', 'EN_PREPARACION')"
                        class="kds-btn kds-btn-iniciar">
                        <i class="ti ti-flame"></i>
                        Iniciar Preparación
                    </button>
                @elseif($comanda->estado === 'EN_PREPARACION')
                    <button type="button"
                        onclick="cambiarEstado(this, '{{ $comanda->_id }}', 'LISTO')"
                        class="kds-btn kds-btn-listo">
                        <i class="ti ti-check"></i>
                        Marcar como Listo
                    </button>
                @endif
            </div>

        </div>
    @empty
        <div class="kds-vacio">
            <i class="ti ti-coffee"></i>
            <p class="kds-vacio-titulo">No hay comandas pendientes</p>
            <p class="kds-vacio-texto">Las nuevas comandas aparecerán aquí automáticamente en tiempo real.</p>
        </div>
    @endforelse
</div>

@endsection

@push('scripts')
<script>
    async function cambiarEstado(boton, idComanda, nuevoEstado) {
        boton.disabled = true;

        try {
            const respuesta = await fetch(`/cocina/actualizar-estado/${idComanda}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': "{{ csrf_token() }}",
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ estado: nuevoEstado })
            });

            const resultado = await respuesta.json();

            if (respuesta.ok && resultado.success) {
                window.location.reload();
            } else {
                alert('Error al actualizar el estado: ' + (resultado.message || 'Error desconocido'));
                boton.disabled = false;
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Ocurrió un error de red al comunicarse con el servidor.');
            boton.disabled = false;
        }
    }
</script>
@endpush
