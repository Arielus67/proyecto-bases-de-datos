@php
    $hora = (int) now()->format('G');
    $saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
@endphp
<div class="bienvenida" id="bienvenida">
    <div class="bienvenida__marca" aria-hidden="true">
        <span class="bienvenida__logo"><img src="{{ asset('img/marca/emblema-claro.svg') }}" alt="" width="64" height="64"></span>
        <span class="bienvenida__nombre">{{ mb_strtoupper(config('app.name')) }}</span>
        <span class="bienvenida__carga"></span>
    </div>
    <p class="bienvenida__saludo">{{ $saludo }}, {{ auth()->user()?->nombre }}</p>
</div>
