@extends('app')

@section('titulo', 'Pantalla de Cocina - Pedidos Pendientes')

@section('botones')
    <button type="button" class="btn btn-light btn-sm fw-bold" onclick="location.reload()">
        <i class="fas fa-sync-alt me-1"></i> Actualizar Pantalla
    </button>
@endsection

@section('contenido')
<div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
    
    {{-- Iteramos sobre los pedidos que la BD indique que están pendientes/en preparación --}}
    @forelse($pedidos as $pedido)
        <div class="col">
            {{-- Cambiamos el color del borde superior dependiendo del estado del pedido --}}
            <div class="card h-100 shadow-sm border-0 border-top border-4 {{ $pedido->estado == 'EN_PREPARACION' ? 'border-info' : 'border-warning' }}">
                
                {{-- Cabecera del Ticket --}}
                <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 pt-3">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="fas fa-hashtag me-1 text-muted"></i>{{ $pedido->id_pedido }}
                    </h5>
                    <span class="badge {{ $pedido->estado == 'EN_PREPARACION' ? 'bg-info' : 'bg-warning text-dark' }} fs-6">
                        Mesa {{ $pedido->mesa->numero_mesa ?? 'N/A' }}
                    </span>
                </div>
                
                <div class="card-body pt-2 pb-0">
                    <div class="d-flex justify-content-between small text-muted mb-3">
                        <span><i class="fas fa-clock me-1"></i> {{ \Carbon\Carbon::parse($pedido->fecha_hora)->format('h:i A') }}</span>
                        <span><i class="fas fa-user me-1"></i> Mesero ID: {{ $pedido->id_empleado }}</span>
                    </div>

                    {{-- Lista de Platillos a preparar --}}
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                @foreach($pedido->detalles as $detalle)
                                    <tr class="border-bottom">
                                        <td class="fw-bold fs-5 text-center align-middle" style="width: 15%;">
                                            {{ $detalle->cantidad }}x
                                        </td>
                                        <td class="align-middle">
                                            <span class="fw-bold">{{ $detalle->producto->nombre_producto ?? 'Producto Desconocido' }}</span>
                                            
                                            {{-- Notas especiales para el cocinero --}}
                                            @if($detalle->notas)
                                                <div class="text-danger small fw-bold mt-1">
                                                    <i class="fas fa-exclamation-circle me-1"></i> {{ $detalle->notas }}
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Observaciones generales del pedido (si existen) --}}
                    @if($pedido->observaciones)
                        <div class="alert alert-light border border-danger text-danger mt-3 mb-0 p-2 small fw-bold">
                            <i class="fas fa-comment-dots me-1"></i> Nota General: {{ $pedido->observaciones }}
                        </div>
                    @endif
                </div>

                {{-- Acciones del Cocinero --}}
                <div class="card-footer bg-white border-top-0 pb-3 pt-3">
                    <form action="{{ route('cocina.update', $pedido->id_pedido) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        @if($pedido->estado == 'ABIERTO' || $pedido->estado == 'PENDIENTE')
                            <input type="hidden" name="nuevo_estado" value="EN_PREPARACION">
                            <button type="submit" class="btn btn-info w-100 fw-bold text-white">
                                <i class="fas fa-fire me-1"></i> Empezar a Preparar
                            </button>
                        @else
                            <input type="hidden" name="nuevo_estado" value="LISTO">
                            <button type="submit" class="btn btn-success w-100 fw-bold">
                                <i class="fas fa-check-circle me-1"></i> Marcar como Listo
                            </button>
                        @endif
                    </form>
                </div>

            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="alert alert-success text-center shadow-sm py-5 mt-4">
                <i class="fas fa-utensilios fa-3x mb-3 text-success d-block"></i>
                <h4 class="fw-bold mb-1">¡Excelente trabajo!</h4>
                <p class="mb-0 text-muted">No hay pedidos pendientes en la cola de cocina.</p>
            </div>
        </div>
    @endforelse

</div>
@endsection

@push('scripts')
<script>
    // Refrescar la página automáticamente cada 30 segundos para ver nuevos pedidos
    setTimeout(function() {
        location.reload();
    }, 30000);
</script>
@endpush