<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'rol.sql' => \App\Http\Middleware\AplicarRolSql::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\IdentificarUsuarioSql::class,
            \App\Http\Middleware\VerificarSesionUnica::class,
        ]);

        $middleware->encryptCookies(except: ['menu_contraido']);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('inicio'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
