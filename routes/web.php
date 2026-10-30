
<?php

use Illuminate\Support\Facades\Route;

// CONTROLADORES DEL SISTEMA
use App\Http\Controllers\ControllerMesa;
use App\Http\Controllers\ControllerCocina;
use App\Http\Controllers\ControllerCaja;
use App\Http\Controllers\ControllerProductos;

// CONTROLADORES ADMINISTRATIVOS
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\EstacionController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\MetodoPagoController;
use App\Http\Controllers\MesaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\InsumoController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\RecetaController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\TurnoCajaController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\AlertaStockController;


/*
|--------------------------------------------------------------------------
| PANEL ADMINISTRATIVO
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->name('admin.')->group(function () {

    // DASHBOARD
    Route::get('/', [DashboardController::class, 'index'])
        ->name('dashboard');

    // ADMINISTRACIÓN DE PERSONAL
    Route::resource('roles', RolController::class)
        ->except('show')
        ->parameters(['roles' => 'rol']);

    Route::resource('empleados', EmpleadoController::class)
        ->except('show');

    // CARTA E INVENTARIO
    Route::resource('categorias', CategoriaController::class)
        ->except('show');

    Route::resource('estaciones', EstacionController::class)
        ->except('show')
        ->parameters(['estaciones' => 'estacion']);

    Route::resource('productos', ProductoController::class)
        ->except('show');

    Route::resource('insumos', InsumoController::class)
        ->except('show');

    Route::resource('recetas', RecetaController::class)
        ->except('show');

    // SALÓN
    Route::resource('mesas', MesaController::class)
        ->except('show');

    Route::resource('clientes', ClienteController::class)
        ->except('show');

    // PEDIDOS
    Route::resource('pedidos', PedidoController::class)
        ->only(['index', 'show']);

    Route::patch('pedidos/{pedido}/anular', [PedidoController::class, 'anular'])
        ->name('pedidos.anular');

    // FACTURACIÓN
    Route::resource('facturas', FacturaController::class)
        ->only(['index', 'show']);

    Route::patch('facturas/{factura}/anular', [FacturaController::class, 'anular'])
        ->name('facturas.anular');

    // PAGOS Y CAJA
    Route::get('pagos', [PagoController::class, 'index'])
        ->name('pagos.index');

    Route::resource('metodos-pago', MetodoPagoController::class)
        ->except('show')
        ->parameters(['metodos-pago' => 'metodo']);

    Route::resource('turnos', TurnoCajaController::class)
        ->except(['show', 'destroy']);

    // AUDITORÍA Y ALERTAS
    Route::get('auditoria', [AuditoriaController::class, 'index'])
        ->name('auditoria.index');

    Route::get('alertas', [AlertaStockController::class, 'index'])
        ->name('alertas.index');

    Route::patch('alertas/{alerta}/atender', [AlertaStockController::class, 'atender'])
        ->name('alertas.atender');

});


/*
|--------------------------------------------------------------------------
| REDIRECCIÓN PRINCIPAL
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('mesero.index');
});


/*
|--------------------------------------------------------------------------
| MÓDULO MESERO
|--------------------------------------------------------------------------
*/

Route::get('/mesero', [ControllerMesa::class, 'index'])
    ->name('mesero.index');

Route::get('/mesero/mesas/{id}/productos', [ControllerMesa::class, 'productosMesa'])
    ->name('mesero.mesas.productos');

Route::post('/mesero/guardar-pedido', [ControllerMesa::class, 'store'])
    ->name('mesero.guardar');


/*
|--------------------------------------------------------------------------
| MÓDULO COCINA
|--------------------------------------------------------------------------
*/

Route::resource('cocina', ControllerCocina::class);

Route::put('/cocina/actualizar-estado/{id}', [ControllerCocina::class, 'update'])
    ->name('cocina.actualizar');


/*
|--------------------------------------------------------------------------
| MÓDULO CAJA
|--------------------------------------------------------------------------
*/

Route::resource('caja', ControllerCaja::class);


/*
|--------------------------------------------------------------------------
| MÓDULO PRODUCTOS INDEPENDIENTE
|--------------------------------------------------------------------------
*/

Route::resource('productos', ControllerProductos::class);

