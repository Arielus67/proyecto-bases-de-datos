@auth
    <div class="flex items-center gap-3">
        <span class="text-sm font-medium">{{ auth()->user()->nombre }} {{ auth()->user()->apellido }} · {{ auth()->user()->rol->nombre_rol ?? $etiqueta }}</span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" title="Cerrar sesión" class="text-sm text-gray-500 hover:text-primary">
                <i class="ti ti-logout"></i> Salir
            </button>
        </form>
    </div>
@else
    <span class="text-sm font-medium">{{ $etiqueta }}</span>
@endauth
