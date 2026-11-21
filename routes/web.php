<?php

use App\Http\Controllers\AlertaStockController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ControllerCaja;
use App\Http\Controllers\ControllerCocina;
use App\Http\Controllers\ControllerMesa;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\EstacionController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\InsumoController;
use App\Http\Controllers\MesaController;
use App\Http\Controllers\MetodoPagoController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\RecetaController;
use App\Http\Controllers\ReporteVentasController;
use App\Http\Controllers\RespaldoController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\TurnoCajaController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/sesion/latido', fn () => response()->noContent())->name('sesion.latido');
    Route::get('/', [AuthController::class, 'inicio'])->name('inicio');
});

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->middleware('can:dashboard.ver')->name('dashboard');

    $catalogos = [
        'roles' => [RolController::class, 'rol', 'roles'],
        'empleados' => [EmpleadoController::class, 'empleado', 'empleados'],
        'categorias' => [CategoriaController::class, 'categoria', 'categorias'],
        'estaciones' => [EstacionController::class, 'estacion', 'estaciones'],
        'productos' => [ProductoController::class, 'producto', 'productos'],
        'insumos' => [InsumoController::class, 'insumo', 'insumos'],
        'recetas' => [RecetaController::class, 'receta', 'recetas'],
        'mesas' => [MesaController::class, 'mesa', 'mesas'],
        'clientes' => [ClienteController::class, 'cliente', 'clientes'],
        'metodos-pago' => [MetodoPagoController::class, 'metodo', 'metodos_pago'],
    ];

    foreach ($catalogos as $modulo => [$controlador, $parametro, $permiso]) {
        Route::resource($modulo, $controlador)
            ->except('show')
            ->parameters([$modulo => $parametro])
            ->middlewareFor('index', "can:{$permiso}.ver")
            ->middlewareFor(['create', 'store'], "can:{$permiso}.crear")
            ->middlewareFor(['edit', 'update'], "can:{$permiso}.editar")
            ->middlewareFor('destroy', "can:{$permiso}.eliminar");
    }

    Route::delete('empleados/{empleado}/sesion', [EmpleadoController::class, 'cerrarSesion'])->middleware('can:empleados.editar')->name('empleados.cerrar-sesion');

    Route::resource('turnos', TurnoCajaController::class)
        ->only(['index', 'create', 'store', 'edit', 'update'])
        ->middlewareFor('index', 'can:turnos.ver')
        ->middlewareFor(['create', 'store'], 'can:turnos.crear')
        ->middlewareFor(['edit', 'update'], 'can:turnos.editar');

    Route::middleware('can:pedidos.ver')->group(function () {
        Route::get('pedidos', [PedidoController::class, 'index'])->name('pedidos.index');
        Route::get('pedidos/{pedido}', [PedidoController::class, 'show'])->name('pedidos.show');
    });
    Route::patch('pedidos/{pedido}/anular', [PedidoController::class, 'anular'])->middleware('can:pedidos.anular')->name('pedidos.anular');

    Route::middleware('can:facturas.ver')->group(function () {
        Route::get('facturas', [FacturaController::class, 'index'])->name('facturas.index');
        Route::get('facturas/{factura}', [FacturaController::class, 'show'])->name('facturas.show');
    });
    Route::patch('facturas/{factura}/anular', [FacturaController::class, 'anular'])->middleware('can:facturas.anular')->name('facturas.anular');

    Route::get('pagos', [PagoController::class, 'index'])->middleware('can:pagos.ver')->name('pagos.index');
    Route::get('reportes/ventas', [ReporteVentasController::class, 'index'])->middleware('can:reportes.ver')->name('reportes.ventas');
    Route::get('auditoria', [AuditoriaController::class, 'index'])->middleware('can:auditoria.ver')->name('auditoria.index');
    Route::get('alertas', [AlertaStockController::class, 'index'])->middleware('can:alertas.ver')->name('alertas.index');
    Route::patch('alertas/{alerta}/atender', [AlertaStockController::class, 'atender'])->middleware('can:alertas.atender')->name('alertas.atender');

    Route::get('respaldos', [RespaldoController::class, 'index'])->middleware('can:respaldos.ver')->name('respaldos.index');
    Route::post('respaldos', [RespaldoController::class, 'store'])->middleware(['can:respaldos.generar', 'throttle:3,1'])->name('respaldos.store');
    Route::post('respaldos/{respaldo}/verificar', [RespaldoController::class, 'verificar'])->middleware(['can:respaldos.generar', 'throttle:10,1'])->name('respaldos.verificar');
    Route::get('respaldos/restaurar', [RespaldoController::class, 'confirmarRestauracion'])->middleware('can:respaldos.restaurar')->name('respaldos.restaurar');
    Route::post('respaldos/restaurar', [RespaldoController::class, 'restaurar'])->middleware(['can:respaldos.restaurar', 'throttle:3,1'])->name('respaldos.restaurar.ejecutar');
});

Route::middleware(['auth', 'can:mesero.usar', 'rol.sql:mesero'])->group(function () {
    Route::get('/mesero', [ControllerMesa::class, 'index'])->name('mesero.index');
    Route::get('/mesero/mesas/{id}/productos', [ControllerMesa::class, 'productosMesa'])->name('mesero.mesas.productos');
    Route::post('/mesero/guardar-pedido', [ControllerMesa::class, 'store'])->name('mesero.guardar');
    Route::post('/mesero/pedidos/{id}/agregar', [ControllerMesa::class, 'agregar'])->name('mesero.agregar');
    Route::patch('/mesero/pedidos/{id}/entregar', [ControllerMesa::class, 'entregar'])->name('mesero.entregar');
});

Route::middleware(['auth', 'can:cocina.usar', 'rol.sql:cocinero'])->group(function () {
    Route::get('/cocina', [ControllerCocina::class, 'index'])->name('cocina.index');
    Route::put('/cocina/actualizar-estado/{id}', [ControllerCocina::class, 'update'])->name('cocina.actualizar');
});

Route::middleware(['auth', 'can:caja.usar', 'rol.sql:cajero'])->group(function () {
    Route::get('/caja', [ControllerCaja::class, 'index'])->name('caja.index');
    Route::get('/caja/facturas', [ControllerCaja::class, 'facturas'])->name('caja.facturas');
    Route::post('/caja/cobrar/{id}', [ControllerCaja::class, 'cobrar'])->name('caja.cobrar');
    Route::get('/caja/ticket/{numero}', [ControllerCaja::class, 'ticket'])->name('caja.ticket');
    Route::post('/caja/turnos/abrir', [ControllerCaja::class, 'abrirTurno'])->name('caja.turno.abrir');
    Route::get('/caja/turnos/{id}/resumen', [ControllerCaja::class, 'resumenTurno'])->name('caja.turno.resumen');
    Route::post('/caja/turnos/{id}/cerrar', [ControllerCaja::class, 'cerrarTurno'])->name('caja.turno.cerrar');
});
