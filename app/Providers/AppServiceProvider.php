<?php

namespace App\Providers;

use App\Models\Empleado;
use App\Support\Acceso;
use Illuminate\Support\Facades\Blade;
use App\View\Composers\RecursosPlantilla;
use Illuminate\Support\Facades\Gate;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.sistema');

        View::composer(['layouts.app', 'layouts.operativo'], RecursosPlantilla::class);

        Blade::if('accesible', fn (?string $url, string $metodo = 'GET') => Acceso::permite($url, $metodo));

        Gate::before(fn (Empleado $empleado, string $permiso) => $empleado->tienePermiso($permiso) ?: null);
    }
}
