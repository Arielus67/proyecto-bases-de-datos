<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ControllerMesa;
use App\Http\Controllers\ControllerCocina;
use App\Http\Controllers\ControllerCaja;
use App\Http\Controllers\ControllerProductos;

Route::get('/', function () {
    return redirect('mesero');
});

Route::resource('mesero', ControllerMesa::class);
Route::resource('cocina', ControllerCocina::class);
Route::resource('caja', ControllerCaja::class);
Route::resource('productos', ControllerProductos::class);
