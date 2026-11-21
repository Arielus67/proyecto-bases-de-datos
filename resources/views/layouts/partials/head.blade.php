<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#234C3B">

<title>@yield('titulo', 'Inicio') · {{ config('app.name') }}</title>

<link rel="shortcut icon" href="{{ asset('img/marca/favicon.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400..800&family=JetBrains+Mono:wght@500;700&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css">

<link rel="stylesheet" href="{{ asset('css/sistema/tokens.css') }}">
<link rel="stylesheet" href="{{ asset('css/sistema/base.css') }}">
<link rel="stylesheet" href="{{ asset('css/sistema/animaciones.css') }}">
<link rel="stylesheet" href="{{ asset('css/sistema/componentes.css') }}">
