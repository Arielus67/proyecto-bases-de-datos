@extends('app')

@section('titulo', 'Mantenimiento de Productos y Menú')

@section('botones')
    {{-- Botón para ir a la vista de crear un nuevo producto --}}
    <a href="{{ route('productos.create') }}" class="btn btn-success btn-sm fw-bold shadow-sm">
        <i class="fas fa-plus me-1"></i> Nuevo Producto
    </a>
@endsection

@section('contenido')
<div class="card shadow-sm border-0">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0 fs-6"><i class="fas fa-list me-2"></i>Catálogo de Platillos y Bebidas</h5>
    </div>
    <div class="card-body">
        
        <div class="table-responsive">
            {{-- La clase 'datatable' activará automáticamente tu script de DataTables --}}
            <table class="table table-striped table-hover align-middle datatable" style="width:100%">
                <thead class="table-light">
                    <tr>
                        <th class="text-center">ID</th>
                        <th>Nombre del Producto</th>
                        <th>Categoría</th>
                        <th>Estación de Preparación</th>
                        <th class="text-end">Precio</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($productos as $producto)
                        <tr>
                            <td class="text-center text-muted fw-bold">{{ $producto->id_producto }}</td>
                            <td class="fw-bold text-primary">{{ $producto->nombre_producto }}</td>
                            
                            {{-- Se asume que el modelo Producto tiene la relación a Categoria --}}
                            <td>{{ $producto->categoria->nombre_categoria ?? 'Sin Categoría' }}</td>
                            
                            {{-- Se asume que el modelo Producto tiene la relación a Estacion --}}
                            <td>
                                <span class="badge bg-secondary">
                                    <i class="fas fa-fire me-1"></i> {{ $producto->estacion->nombre_estacion ?? 'N/A' }}
                                </span>
                            </td>
                            
                            <td class="text-end fw-bold">₡{{ number_format($producto->precio, 0, ',', '.') }}</td>
                            
                            <td class="text-center">
                                @if($producto->activo == 1)
                                    <span class="badge bg-success">Activo</span>
                                @else
                                    <span class="badge bg-danger">Inactivo</span>
                                @endif
                            </td>
                            
                            <td class="text-center">
                                {{-- Botón Editar --}}
                                <a href="{{ route('productos.edit', $producto->id_producto) }}" class="btn btn-sm btn-outline-primary" title="Editar Producto">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                {{-- Botón Eliminar / Desactivar --}}
                                <form action="{{ route('productos.destroy', $producto->id_producto) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de que desea cambiar el estado de este producto?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Desactivar Producto">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="fas fa-exclamation-triangle me-2"></i>No hay productos registrados en el sistema.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</div>
@endsection