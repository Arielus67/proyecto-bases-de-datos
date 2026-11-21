<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\EsquemaSqlite;
use Tests\TestCase;

class PanelAdminTest extends TestCase
{
    use EsquemaSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBaseDeDatos();
        $this->sembrarDatosBase();
        $this->sembrarVentas();
    }

    protected function tearDown(): void
    {
        $this->eliminarBaseDeDatos();

        parent::tearDown();
    }

    private function sembrarVentas(): void
    {
        $bd = DB::connection('sqlsrv');

        $mesero = $this->crearEmpleado('Mesero', 'mesero@prueba.test');
        $cajero = $this->crearEmpleado('Cajero', 'cajero@prueba.test');
        $admin = $this->crearEmpleado('Administrador', 'admin@prueba.test');

        $this->iniciarSesionComo($admin);

        $turno = $bd->table('TURNO_CAJA')->insertGetId(['id_empleado' => $cajero, 'monto_inicial' => 10000], 'id_turno');

        $bd->table('PEDIDO')->insert([
            ['token_idempotencia' => 't-1', 'id_mesa' => 1, 'id_empleado' => $mesero, 'estado' => 'FACTURADO', 'subtotal' => 6000, 'anulado_por' => null, 'motivo_anulacion' => null],
            ['token_idempotencia' => 't-2', 'id_mesa' => 2, 'id_empleado' => $mesero, 'estado' => 'ANULADO', 'subtotal' => 1500, 'anulado_por' => $admin, 'motivo_anulacion' => 'Cliente se fue'],
        ]);

        $bd->table('DETALLE_PEDIDO')->insert([
            ['id_pedido' => 1, 'num_linea' => 1, 'id_producto' => 1, 'cantidad' => 1, 'precio_unitario' => 4500, 'estado_cocina' => 'ENTREGADO'],
            ['id_pedido' => 1, 'num_linea' => 2, 'id_producto' => 2, 'cantidad' => 1, 'precio_unitario' => 1500, 'estado_cocina' => 'ENTREGADO'],
            ['id_pedido' => 2, 'num_linea' => 1, 'id_producto' => 2, 'cantidad' => 1, 'precio_unitario' => 1500, 'estado_cocina' => 'CANCELADO'],
        ]);

        $bd->table('FACTURA')->insert([
            ['numero_factura' => 'FAC-00000001', 'id_pedido' => 1, 'id_empleado' => $cajero, 'id_turno' => $turno, 'subtotal' => 6000, 'descuento' => 0, 'impuesto_ventas' => 780, 'servicio' => 600, 'total' => 7380, 'vuelto' => 620, 'estado' => 'COBRADA', 'anulada_por' => null, 'motivo_anulacion' => null, 'fecha_anulacion' => null],
            ['numero_factura' => 'FAC-00000002', 'id_pedido' => 2, 'id_empleado' => $cajero, 'id_turno' => $turno, 'subtotal' => 1500, 'descuento' => 0, 'impuesto_ventas' => 195, 'servicio' => 150, 'total' => 1845, 'vuelto' => 0, 'estado' => 'ANULADA', 'anulada_por' => $admin, 'motivo_anulacion' => 'Error de cobro', 'fecha_anulacion' => now()->toDateTimeString()],
        ]);

        $bd->table('PAGO')->insert([
            ['id_factura' => 1, 'id_metodo_pago' => 1, 'monto' => 8000],
        ]);
    }

    public static function listadosAdmin(): array
    {
        return array_map(fn ($ruta) => [$ruta], [
            'admin.roles.index',
            'admin.empleados.index',
            'admin.categorias.index',
            'admin.estaciones.index',
            'admin.productos.index',
            'admin.insumos.index',
            'admin.recetas.index',
            'admin.mesas.index',
            'admin.clientes.index',
            'admin.pedidos.index',
            'admin.facturas.index',
            'admin.pagos.index',
            'admin.metodos-pago.index',
            'admin.turnos.index',
            'admin.auditoria.index',
            'admin.alertas.index',
        ]);
    }

    #[DataProvider('listadosAdmin')]
    public function test_cada_listado_del_panel_responde(string $ruta): void
    {
        $this->get(route($ruta))->assertOk();
    }

    public static function formulariosAdmin(): array
    {
        return [
            ['admin.roles.create', []],
            ['admin.roles.edit', [1]],
            ['admin.empleados.create', []],
            ['admin.empleados.edit', [1]],
            ['admin.categorias.create', []],
            ['admin.categorias.edit', [1]],
            ['admin.estaciones.create', []],
            ['admin.estaciones.edit', [1]],
            ['admin.productos.create', []],
            ['admin.productos.edit', [1]],
            ['admin.insumos.create', []],
            ['admin.insumos.edit', [1]],
            ['admin.recetas.create', []],
            ['admin.mesas.create', []],
            ['admin.mesas.edit', [1]],
            ['admin.clientes.create', []],
            ['admin.clientes.edit', [1]],
            ['admin.metodos-pago.create', []],
            ['admin.metodos-pago.edit', [1]],
            ['admin.turnos.create', []],
            ['admin.turnos.edit', [1]],
        ];
    }

    #[DataProvider('formulariosAdmin')]
    public function test_cada_formulario_de_crear_y_editar_responde(string $ruta, array $parametros): void
    {
        DB::connection('sqlsrv')->table('INSUMO')->insert(['nombre_insumo' => 'Arroz', 'unidad_medida' => 'kg', 'stock_actual' => 10, 'stock_minimo' => 2, 'costo_unitario' => 900]);
        DB::connection('sqlsrv')->table('CLIENTE')->insert(['cedula' => '123456789', 'nombre' => 'Ana', 'apellido' => 'Mora']);

        $this->get(route($ruta, $parametros))->assertOk();
    }

    public function test_detalle_de_pedido_facturado_muestra_sus_lineas(): void
    {
        $this->get(route('admin.pedidos.show', 1))
            ->assertOk()
            ->assertSee('Casado')
            ->assertSee('Refresco natural');
    }

    public function test_detalle_de_pedido_anulado_muestra_quien_lo_anulo_y_el_motivo(): void
    {
        $this->get(route('admin.pedidos.show', 2))
            ->assertOk()
            ->assertSee('Anulado por')
            ->assertSee('Administrador')
            ->assertSee('Cliente se fue');
    }

    public function test_detalle_de_factura_muestra_cajero_y_pagos(): void
    {
        $this->get(route('admin.facturas.show', 1))
            ->assertOk()
            ->assertSee('FAC-00000001')
            ->assertSee('Cajero')
            ->assertSee('Efectivo');
    }

    public function test_detalle_de_factura_anulada_muestra_quien_la_anulo(): void
    {
        $this->get(route('admin.facturas.show', 2))
            ->assertOk()
            ->assertSee('Anulada por')
            ->assertSee('Error de cobro');
    }

    public function test_listado_de_facturas_muestra_el_nombre_del_cajero(): void
    {
        $this->get(route('admin.facturas.index'))
            ->assertOk()
            ->assertSee('FAC-00000001')
            ->assertSee('Cajero');
    }

    public function test_pedido_o_factura_inexistente_responde_404(): void
    {
        $this->get(route('admin.pedidos.show', 999))->assertNotFound();
        $this->get(route('admin.facturas.show', 999))->assertNotFound();
    }
}
