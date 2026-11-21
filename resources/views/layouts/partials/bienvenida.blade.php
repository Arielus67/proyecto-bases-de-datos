@php
    $hora = (int) now()->format('G');
    $saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
@endphp
<div class="bienvenida" id="bienvenida">
    <div class="bienvenida__marca" aria-hidden="true">
        <span class="bienvenida__logo"><i class="ti ti-tools-kitchen-2"></i></span>
        <span class="bienvenida__nombre">{{ mb_strtoupper(config('app.name')) }}</span>
        <span class="bienvenida__carga"></span>
    </div>
    <p class="bienvenida__saludo">{{ $saludo }}, {{ auth()->user()?->nombre }}</p>
</div>
