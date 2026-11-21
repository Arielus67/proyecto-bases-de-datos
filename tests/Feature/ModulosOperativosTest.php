<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\EsquemaSqlite;
use Tests\TestCase;

class ModulosOperativosTest extends TestCase
{
    use EsquemaSqlite;

    private int $admin;
    private int $mesero;
    private int $cajero;
    private int $otroCajero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBaseDeDatos();
        $this->sembrarDatosBase();

        $this->admin = $this->crearEmpleado('Administrador', 'admin@prueba.test');
        $this->mesero = $this->crearEmpleado('Mesero', 'mesero@prueba.test');
        $this->cajero = $this->crearEmpleado('Cajero', 'cajero@prueba.test');
        $this->otroCajero = $this->crearEmpleado('Cajero', 'cajero2@prueba.test');

        DB::connection('sqlsrv')->table('EMPLEADO')->where('id_empleado', $this->otroCajero)->update(['nombre' => 'Lucía', 'apellido' => 'Vargas']);
    }

    protected function tearDown(): void
    {
        $this->eliminarBaseDeDatos();

        parent::tearDown();
    }

    private function bd()
    {
        return DB::connection('sqlsrv');
    }

    private function crearPedidoListo(int $mesa = 1, string $estado = 'LISTO'): int
    {
        $id = $this->bd()->table('PEDIDO')->insertGetId([
            'token_idempotencia' => 'tok-' . uniqid(),
            'id_mesa' => $mesa,
            'id_empleado' => $this->mesero,
            'estado' => $estado,
            'subtotal' => 6000,
        ], 'id_pedido');

        $this->bd()->table('DETALLE_PEDIDO')->insert([
            ['id_pedido' => $id, 'num_linea' => 1, 'id_producto' => 1, 'cantidad' => 1, 'precio_unitario' => 4500, 'estado_cocina' => $estado],
            ['id_pedido' => $id, 'num_linea' => 2, 'id_producto' => 2, 'cantidad' => 1, 'precio_unitario' => 1500, 'estado_cocina' => $estado],
        ]);

        $this->bd()->table('MESA')->where('id_mesa', $mesa)->update(['estado' => 'OCUPADA']);

        return $id;
    }

    private function abrirTurno(int $cajero): int
    {
        return $this->bd()->table('TURNO_CAJA')->insertGetId(['id_empleado' => $cajero, 'monto_inicial' => 10000], 'id_turno');
    }

    private function emitirFactura(int $cajero, int $turno, string $numero): void
    {
        $pedido = $this->crearPedidoListo(2, 'FACTURADO');

        $this->bd()->table('FACTURA')->insert([
            'numero_factura' => $numero, 'id_pedido' => $pedido, 'id_empleado' => $cajero, 'id_turno' => $turno,
            'subtotal' => 6000, 'descuento' => 0, 'impuesto_ventas' => 780, 'servicio' => 600, 'total' => 7380, 'vuelto' => 0, 'estado' => 'COBRADA',
        ]);
    }

    public function test_menu_del_mesero_agrupa_por_categoria_con_busqueda_y_filtros(): void
    {
        $this->iniciarSesionComo($this->mesero);

        $this->get(route('mesero.mesas.productos', 1))
            ->assertOk()
            ->assertSee('id="menu-buscar"', false)
            ->assertSee('data-categoria="todas"', false)
            ->assertSeeInOrder(['Bebidas', 'Refresco natural', 'Platos fuertes', 'Casado', 'Pargo frito'])
            ->assertSee('class="menu-grid"', false)
            ->assertSee('data-nombre="Pargo frito"', false);
    }

    public function test_menu_no_muestra_productos_inactivos(): void
    {
        $this->bd()->table('PRODUCTO')->where('nombre_producto', 'Pargo frito')->update(['activo' => 0]);
        $this->iniciarSesionComo($this->mesero);

        $this->get(route('mesero.mesas.productos', 1))
            ->assertOk()
            ->assertDontSee('Pargo frito');
    }

    public function test_vista_de_mesero_responde_parcial_en_peticiones_de_sondeo(): void
    {
        $this->crearPedidoListo();
        $this->iniciarSesionComo($this->mesero);

        $respuesta = $this->get(route('mesero.index'), ['X-Requested-With' => 'XMLHttpRequest']);

        $respuesta->assertOk()
            ->assertSee('Listos para entregar')
            ->assertSee('mesa-card', false)
            ->assertDontSee('<html', false);
    }

    public function test_vista_completa_de_mesero_incluye_el_sondeo_y_no_envia_id_empleado_fijo(): void
    {
        $this->iniciarSesionComo($this->mesero);

        $this->get(route('mesero.index'))
            ->assertOk()
            ->assertSee('js/sondeo.js', false)
            ->assertSee('js/modulos/mesero.js', false)
            ->assertSee('id="datos-mesero"', false)
            ->assertDontSee('id_empleado: 1', false);
    }

    public function test_entregar_un_pedido_listo_y_rechazar_la_segunda_entrega(): void
    {
        $pedido = $this->crearPedidoListo();
        $this->iniciarSesionComo($this->mesero);

        $this->patchJson(route('mesero.entregar', $pedido))->assertOk()->assertJson(['success' => true]);
        $this->assertSame('ENTREGADO', $this->bd()->table('PEDIDO')->where('id_pedido', $pedido)->value('estado'));
        $this->assertSame(2, $this->bd()->table('DETALLE_PEDIDO')->where('id_pedido', $pedido)->where('estado_cocina', 'ENTREGADO')->count());

        $this->patchJson(route('mesero.entregar', $pedido))->assertStatus(409);
    }

    public function test_registrar_pedido_exige_detalles_validos(): void
    {
        $this->iniciarSesionComo($this->mesero);

        $this->postJson(route('mesero.guardar'), [
            'token_idempotencia' => 'tok-x',
            'id_mesa' => 1,
            'detalles' => [['num_linea' => 1, 'id_producto' => 1, 'cantidad' => 0]],
        ])->assertStatus(422)->assertJsonValidationErrors('detalles.0.cantidad');
    }

    public function test_cajero_sin_turno_ve_la_facturacion_y_un_aviso_sin_enlace_para_abrir_turno(): void
    {
        $this->crearPedidoListo();
        $this->iniciarSesionComo($this->cajero);

        $this->get(route('caja.index'))
            ->assertOk()
            ->assertSee('Pedidos pendientes de cobro')
            ->assertSee('Mesa 1')
            ->assertSee('Solicita al administrador')
            ->assertDontSee(route('admin.turnos.create'), false);
    }

    public function test_administrador_sin_turnos_abiertos_ve_el_enlace_para_abrir_turno(): void
    {
        $this->iniciarSesionComo($this->admin);

        $this->get(route('caja.index'))
            ->assertOk()
            ->assertSee(route('admin.turnos.create'), false);
    }

    public function test_cajero_solo_ve_su_propio_turno_en_caja(): void
    {
        $this->abrirTurno($this->cajero);
        $this->abrirTurno($this->otroCajero);
        $this->iniciarSesionComo($this->cajero);

        $this->get(route('caja.index'))
            ->assertOk()
            ->assertDontSee('Lucía Vargas')
            ->assertDontSee('Solicita al administrador');
    }

    public function test_cajero_no_puede_consultar_ni_cerrar_el_turno_de_otro(): void
    {
        $turnoAjeno = $this->abrirTurno($this->otroCajero);
        $this->iniciarSesionComo($this->cajero);

        $this->getJson(route('caja.turno.resumen', $turnoAjeno))->assertForbidden();
        $this->postJson(route('caja.turno.cerrar', $turnoAjeno), ['monto_final' => 1000])->assertForbidden();

        $this->assertNull($this->bd()->table('TURNO_CAJA')->where('id_turno', $turnoAjeno)->value('fecha_cierre'));
    }

    public function test_cajero_cierra_su_propio_turno_una_sola_vez(): void
    {
        $turno = $this->abrirTurno($this->cajero);
        $this->iniciarSesionComo($this->cajero);

        $this->getJson(route('caja.turno.resumen', $turno))->assertOk()->assertJson(['success' => true, 'esperado' => 10000]);
        $this->postJson(route('caja.turno.cerrar', $turno), ['monto_final' => 10000])->assertOk()->assertJson(['diferencia' => 0]);
        $this->postJson(route('caja.turno.cerrar', $turno), ['monto_final' => 10000])->assertStatus(409);
    }

    public function test_caja_responde_parcial_con_datos_en_peticiones_de_sondeo(): void
    {
        $pedido = $this->crearPedidoListo();
        $this->iniciarSesionComo($this->cajero);

        $this->get(route('caja.index'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('id="caja-datos"', false)
            ->assertSee('"id":' . $pedido, false)
            ->assertDontSee('<html', false);
    }

    public function test_cajero_ve_solo_sus_facturas_y_tickets(): void
    {
        $turno = $this->abrirTurno($this->cajero);
        $turnoAjeno = $this->abrirTurno($this->otroCajero);
        $this->emitirFactura($this->cajero, $turno, 'FAC-PROPIA');
        $this->emitirFactura($this->otroCajero, $turnoAjeno, 'FAC-AJENA');
        $this->iniciarSesionComo($this->cajero);

        $this->get(route('caja.facturas'))
            ->assertOk()
            ->assertSee('FAC-PROPIA')
            ->assertDontSee('FAC-AJENA');

        $this->get(route('caja.ticket', 'FAC-PROPIA'))->assertOk();
        $this->get(route('caja.ticket', 'FAC-AJENA'))->assertForbidden();
    }

    public function test_ticket_recien_emitido_inicia_la_animacion_de_impresion(): void
    {
        $turno = $this->abrirTurno($this->cajero);
        $this->emitirFactura($this->cajero, $turno, 'FAC-0042');
        $this->iniciarSesionComo($this->cajero);

        $this->get(route('caja.ticket', ['numero' => 'FAC-0042', 'nuevo' => 1]))
            ->assertOk()
            ->assertSee('data-fase="preparando"', false)
            ->assertSee('Cobro completado')
            ->assertSee('aria-label="Código de barras FAC-0042"', false)
            ->assertSee('js/modulos/ticket.js', false);

        $this->get(route('caja.ticket', 'FAC-0042'))
            ->assertOk()
            ->assertSee('data-fase="reimpresion"', false)
            ->assertDontSee('data-fase="preparando"', false);

        $this->get(route('caja.ticket', ['numero' => 'FAC-0042', 'ancho' => 58]))
            ->assertSee('class="ticket-pagina ticket-58"', false);
    }

    public function test_codigo_de_barras_code39_tiene_la_estructura_correcta(): void
    {
        $codigo = \App\Support\CodigoBarras::code39('fac-12');

        $this->assertSame('FAC-12', \App\Support\CodigoBarras::texto('fac-12'));
        $this->assertSame('FAC12', \App\Support\CodigoBarras::texto('fac_1#2'));
        $this->assertCount(8 * 5, $codigo['barras']);
        $this->assertSame(8 * 15 + 7, $codigo['ancho']);
        $this->assertSame(['x' => 0, 'ancho' => 1], $codigo['barras'][0]);
    }

    public function test_administrador_ve_todas_las_facturas_emitidas(): void
    {
        $this->emitirFactura($this->cajero, $this->abrirTurno($this->cajero), 'FAC-UNO');
        $this->emitirFactura($this->otroCajero, $this->abrirTurno($this->otroCajero), 'FAC-DOS');
        $this->iniciarSesionComo($this->admin);

        $this->get(route('caja.facturas'))->assertOk()->assertSee('FAC-UNO')->assertSee('FAC-DOS');
    }

    public function test_respaldos_muestra_el_historial_al_administrador(): void
    {
        $this->bd()->table('RESPALDO_BD')->insert([
            'nombre_archivo' => 'RestauranteDB_20261105_101500_123.bak',
            'ruta_completa' => 'C:\\Respaldos\\RestauranteDB_20261105_101500_123.bak',
            'id_empleado' => $this->admin,
            'verificado' => 1,
            'resultado' => 'Respaldo generado y verificado correctamente.',
        ]);
        $this->iniciarSesionComo($this->admin);

        $this->get(route('admin.respaldos.index'))
            ->assertOk()
            ->assertSee('RestauranteDB_20261105_101500_123.bak');
    }

    public function test_respaldos_avisan_si_falta_la_tabla_sin_romper_la_pagina(): void
    {
        $this->bd()->statement('DROP TABLE RESPALDO_BD');
        $this->iniciarSesionComo($this->admin);

        $this->get(route('admin.respaldos.index'))
            ->assertOk()
            ->assertSee('1_RestauranteDB_estructura.sql');
    }

    public function test_respaldo_sin_carpeta_configurada_no_se_ejecuta(): void
    {
        config(['restaurante.respaldos.ruta' => null]);
        $this->iniciarSesionComo($this->admin);

        $this->from(route('admin.respaldos.index'))
            ->post(route('admin.respaldos.store'))
            ->assertRedirect(route('admin.respaldos.index'))
            ->assertSessionHas('error');
    }

    public function test_respaldo_no_se_permite_dentro_de_la_carpeta_publica(): void
    {
        config(['restaurante.respaldos.ruta' => public_path('respaldos')]);
        $this->iniciarSesionComo($this->admin);

        $this->from(route('admin.respaldos.index'))
            ->post(route('admin.respaldos.store'))
            ->assertSessionHas('error', 'La carpeta de respaldos no puede estar dentro de la carpeta pública del proyecto.');
    }

    public function test_apertura_de_turno_fallida_en_la_base_no_rompe_la_pagina_ni_inserta_nada(): void
    {
        $this->iniciarSesionComo($this->admin);

        $this->from(route('admin.turnos.create'))
            ->post(route('admin.turnos.store'), ['id_empleado' => $this->cajero, 'monto_inicial' => 5000])
            ->assertRedirect(route('admin.turnos.create'))
            ->assertSessionHas('error');

        $this->assertSame(0, $this->bd()->table('TURNO_CAJA')->count());
    }

    public function test_apertura_de_turno_valida_que_sea_un_cajero(): void
    {
        $this->iniciarSesionComo($this->admin);

        $this->from(route('admin.turnos.create'))
            ->post(route('admin.turnos.store'), ['id_empleado' => $this->mesero, 'monto_inicial' => 5000])
            ->assertSessionHasErrors('id_empleado');
    }

    public function test_factura_se_anula_una_sola_vez(): void
    {
        $this->emitirFactura($this->cajero, $this->abrirTurno($this->cajero), 'FAC-ANULAR');
        $id = $this->bd()->table('FACTURA')->where('numero_factura', 'FAC-ANULAR')->value('id_factura');
        $this->iniciarSesionComo($this->admin);

        $this->from(route('admin.facturas.show', $id))
            ->patch(route('admin.facturas.anular', $id), ['motivo_anulacion' => 'Prueba'])
            ->assertRedirect(route('admin.facturas.show', $id))
            ->assertSessionHas('success');

        $factura = $this->bd()->table('FACTURA')->where('id_factura', $id)->first();
        $this->assertSame('ANULADA', $factura->estado);
        $this->assertSame($this->admin, (int) $factura->anulada_por);

        $this->from(route('admin.facturas.show', $id))
            ->patch(route('admin.facturas.anular', $id), ['motivo_anulacion' => 'Otra vez'])
            ->assertSessionHas('error');

        $this->assertSame('Prueba', $this->bd()->table('FACTURA')->where('id_factura', $id)->value('motivo_anulacion'));
    }

    public function test_no_se_cobra_un_pedido_que_cocina_no_ha_terminado(): void
    {
        $pedido = $this->crearPedidoListo(1, 'EN_PREPARACION');
        $this->abrirTurno($this->cajero);
        $this->iniciarSesionComo($this->cajero);

        $this->postJson(route('caja.cobrar', $pedido), ['pagos' => [['id_metodo_pago' => 1, 'monto' => 10000]]])
            ->assertStatus(409)
            ->assertJsonFragment(['success' => false]);

        $this->assertSame(0, $this->bd()->table('FACTURA')->count());
    }

    public function test_cobro_rechaza_montos_que_no_caben_en_la_base(): void
    {
        $pedido = $this->crearPedidoListo();
        $this->iniciarSesionComo($this->cajero);

        $this->postJson(route('caja.cobrar', $pedido), ['pagos' => [['id_metodo_pago' => 1, 'monto' => 100000000]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('pagos.0.monto');

        $this->postJson(route('caja.cobrar', $pedido), ['pagos' => [['id_metodo_pago' => 1, 'monto' => 10.555]]])
            ->assertJsonValidationErrors('pagos.0.monto');
    }

    public function test_mesa_con_pedido_abierto_no_puede_liberarse_desde_el_panel(): void
    {
        $pedido = $this->crearPedidoListo(1, 'ABIERTO');
        $this->iniciarSesionComo($this->admin);

        $this->from(route('admin.mesas.edit', 1))
            ->put(route('admin.mesas.update', 1), ['numero_mesa' => 1, 'capacidad' => 4, 'zona' => 'Salón', 'estado' => 'DISPONIBLE'])
            ->assertSessionHasErrors(['estado' => 'La mesa tiene abierto el pedido #' . $pedido . '. Factúralo o anúlalo antes de cambiar su estado.']);

        $this->assertSame('OCUPADA', $this->bd()->table('MESA')->where('id_mesa', 1)->value('estado'));
    }

    public function test_mesa_no_se_marca_ocupada_a_mano(): void
    {
        $this->iniciarSesionComo($this->admin);

        $this->from(route('admin.mesas.create'))
            ->post(route('admin.mesas.store'), ['numero_mesa' => 9, 'capacidad' => 4, 'zona' => 'Salón', 'estado' => 'OCUPADA'])
            ->assertSessionHasErrors('estado');

        $this->from(route('admin.mesas.create'))
            ->post(route('admin.mesas.store'), ['numero_mesa' => 9, 'capacidad' => 4, 'zona' => 'Salón', 'estado' => 'DISPONIBLE'])
            ->assertSessionHasNoErrors();
    }

    public function test_producto_rechaza_precio_fuera_del_rango_decimal(): void
    {
        $this->iniciarSesionComo($this->admin);

        $this->from(route('admin.productos.create'))
            ->post(route('admin.productos.store'), ['nombre_producto' => 'Prueba', 'precio' => 123456789, 'id_categoria' => 1, 'id_estacion' => 1])
            ->assertSessionHasErrors('precio');

        $this->from(route('admin.productos.create'))
            ->post(route('admin.productos.store'), ['nombre_producto' => 'Prueba', 'precio' => 1500.505, 'id_categoria' => 1, 'id_estacion' => 1])
            ->assertSessionHasErrors('precio');
    }

    public function test_receta_exige_una_cantidad_por_cada_insumo(): void
    {
        $insumo = $this->bd()->table('INSUMO')->insertGetId(['nombre_insumo' => 'Arroz', 'unidad_medida' => 'kg', 'stock_actual' => 10, 'stock_minimo' => 2, 'costo_unitario' => 900], 'id_insumo');
        $otro = $this->bd()->table('INSUMO')->insertGetId(['nombre_insumo' => 'Frijol', 'unidad_medida' => 'kg', 'stock_actual' => 10, 'stock_minimo' => 2, 'costo_unitario' => 900], 'id_insumo');
        $this->iniciarSesionComo($this->admin);

        $this->from(route('admin.recetas.create'))
            ->post(route('admin.recetas.store'), ['id_producto' => 1, 'insumos' => [$insumo, $otro], 'cantidades' => [0.2]])
            ->assertSessionHasErrors('cantidades');

        if (! \App\Support\Sp::disponibles()) {
            return; // guardar la receta usa procedimientos almacenados: solo se comprueba con SQL Server
        }

        $this->from(route('admin.recetas.create'))
            ->post(route('admin.recetas.store'), ['id_producto' => 1, 'insumos' => [$insumo, $otro], 'cantidades' => [0.2, 0.15]])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $this->bd()->table('RECETA')->where('id_producto', 1)->count());
    }

    public function test_turno_no_se_cierra_antes_de_abrirse(): void
    {
        $turno = $this->bd()->table('TURNO_CAJA')->insertGetId(['id_empleado' => $this->cajero, 'monto_inicial' => 10000, 'fecha_apertura' => '2026-11-05 08:00:00'], 'id_turno');
        $this->iniciarSesionComo($this->admin);

        $this->from(route('admin.turnos.edit', $turno))
            ->put(route('admin.turnos.update', $turno), ['id_empleado' => $this->cajero, 'monto_inicial' => 10000, 'fecha_cierre' => '2026-11-04 20:00:00', 'monto_final' => 10000])
            ->assertSessionHasErrors('fecha_cierre');
    }

    public function test_alerta_de_stock_se_marca_como_atendida(): void
    {
        $insumo = $this->bd()->table('INSUMO')->insertGetId(['nombre_insumo' => 'Arroz', 'unidad_medida' => 'kg', 'stock_actual' => 1, 'stock_minimo' => 2, 'costo_unitario' => 900], 'id_insumo');
        $alerta = $this->bd()->table('ALERTA_STOCK')->insertGetId(['id_insumo' => $insumo, 'stock_actual' => 1, 'stock_minimo' => 2], 'id_alerta');
        $this->iniciarSesionComo($this->admin);

        $this->from(route('admin.alertas.index'))->patch(route('admin.alertas.atender', $alerta))->assertRedirect();

        $this->assertSame(1, (int) $this->bd()->table('ALERTA_STOCK')->where('id_alerta', $alerta)->value('atendida'));
    }
}
