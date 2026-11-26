<?php

namespace Tests\Feature;

use App\Http\Controllers\ControllerMesa;
use App\Models\ComandaNoSql;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\EsquemaSqlite;
use Tests\TestCase;

/**
 * Comandas en MongoDB contra pedidos de SQL Server. Usa un MongoDB real, pero en una base
 * de pruebas aparte (restaurante_mongo_pruebas) que se elimina al terminar.
 */
class ComandasMongoTest extends TestCase
{
    use EsquemaSqlite;

    private int $idCocinero;

    private int $idMesero;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('mongodb')) {
            $this->markTestSkipped('La extensión mongodb de PHP no está instalada.');
        }

        config(['database.connections.mongodb.database' => 'restaurante_mongo_pruebas']);
        DB::purge('mongodb');

        try {
            DB::connection('mongodb')->getMongoDB()->command(['ping' => 1]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB no está disponible: ' . $e->getMessage());
        }

        $this->limpiarMongo();
        $this->prepararBaseDeDatos();
        $this->sembrarDatosBase();
        $this->idCocinero = $this->crearEmpleado('Cocinero', 'cocina@prueba.test');
        $this->idMesero = $this->crearEmpleado('Mesero', 'mesero@prueba.test');
    }

    protected function tearDown(): void
    {
        if (extension_loaded('mongodb')) {
            try {
                DB::connection('mongodb')->getMongoDB()->drop();
            } catch (\Throwable $e) {
            }
        }

        $this->eliminarBaseDeDatos();

        parent::tearDown();
    }

    private function limpiarMongo(): void
    {
        DB::connection('mongodb')->getMongoDB()->drop();
    }

    private function crearPedido(string $token, string $estado = 'ABIERTO'): int
    {
        $sql = DB::connection('sqlsrv');
        $id = (int) $sql->table('PEDIDO')->insertGetId([
            'token_idempotencia' => $token, 'id_mesa' => 1, 'id_empleado' => $this->idMesero, 'estado' => $estado, 'fecha_hora' => now(),
        ], 'id_pedido');
        $sql->table('DETALLE_PEDIDO')->insert([
            'id_pedido' => $id, 'num_linea' => 1, 'id_producto' => 1, 'cantidad' => 2, 'precio_unitario' => 1000, 'estado_cocina' => 'PENDIENTE',
        ]);

        return $id;
    }

    private function comandaVieja(int $idPedido, string $token, string $estado): void
    {
        ComandaNoSql::create([
            'id_pedido_sql' => $idPedido, 'token_idempotencia' => $token, 'id_mesa' => 1, 'id_empleado' => 1,
            'estado' => $estado, 'detalles_con_modificadores' => [['num_linea' => 1, 'id_producto' => 1, 'cantidad' => 9, 'notas' => 'VIEJA', 'ronda' => 1]],
            'ronda_actual' => 1, 'ronda_at' => now()->subMonth(),
        ]);
    }

    private function archivadas(): int
    {
        return DB::connection('mongodb')->table(ComandaNoSql::COLECCION_ARCHIVO)->count();
    }

    public function test_registrar_un_pedido_guarda_su_comanda_en_mongodb(): void
    {
        $id = $this->crearPedido('token-nuevo');

        $crear = new \ReflectionMethod(ControllerMesa::class, 'crearComanda');
        $crear->invoke(app(ControllerMesa::class), $id, [
            'token_idempotencia' => 'token-nuevo', 'id_mesa' => 1, 'id_empleado' => $this->idMesero,
            'detalles' => [['num_linea' => 1, 'id_producto' => 1, 'cantidad' => 2, 'notas' => 'sin cebolla']],
        ]);

        $comanda = ComandaNoSql::where('id_pedido_sql', $id)->first();

        $this->assertNotNull($comanda, 'La comanda no se guardó en MongoDB.');
        $this->assertSame('token-nuevo', $comanda->token_idempotencia);
        $this->assertSame('ABIERTO', $comanda->estado);
        $this->assertSame('sin cebolla', $comanda->detalles_con_modificadores[0]['notas']);
    }

    public function test_si_ya_habia_una_comanda_vieja_con_el_mismo_numero_se_crea_la_correcta(): void
    {
        $id = $this->crearPedido('token-actual');
        $this->comandaVieja($id, 'token-de-otra-base', 'FACTURADO');

        $crear = new \ReflectionMethod(ControllerMesa::class, 'crearComanda');
        $crear->invoke(app(ControllerMesa::class), $id, [
            'token_idempotencia' => 'token-actual', 'id_mesa' => 1, 'id_empleado' => $this->idMesero,
            'detalles' => [['num_linea' => 1, 'id_producto' => 1, 'cantidad' => 2, 'notas' => '']],
        ]);

        $comandas = ComandaNoSql::where('id_pedido_sql', $id)->get();

        $this->assertCount(1, $comandas);
        $this->assertSame('token-actual', $comandas->first()->token_idempotencia);
        $this->assertSame(1, $this->archivadas());
    }

    public function test_comanda_fantasma_no_aparece_en_cocina_y_se_archiva(): void
    {
        $this->comandaVieja(99, 'token-fantasma', 'ABIERTO');
        $this->iniciarSesionComo($this->idCocinero);

        $this->get(route('cocina.index'))->assertOk()->assertDontSee('VIEJA');

        $this->assertSame(0, ComandaNoSql::count());
        $this->assertSame(1, $this->archivadas());
    }

    public function test_cocina_muestra_el_pedido_real_y_no_la_comanda_vieja_con_su_numero(): void
    {
        $id = $this->crearPedido('token-actual');
        $this->comandaVieja($id, 'token-de-otra-base', 'FACTURADO');
        $this->iniciarSesionComo($this->idCocinero);

        $this->get(route('cocina.index'))->assertOk()->assertDontSee('VIEJA');

        $comanda = ComandaNoSql::where('id_pedido_sql', $id)->first();
        $this->assertSame('token-actual', $comanda->token_idempotencia);
        $this->assertSame('ABIERTO', $comanda->estado);
        $this->assertSame(2, (int) $comanda->detalles_con_modificadores[0]['cantidad']);
    }

    /** Crea la comanda (ronda 1, mesero A) y le suma una ronda (mesero B) como hace ControllerMesa. */
    private function pedidoConDosRondas(): int
    {
        $id = $this->crearPedido('token-dos-meseros');
        $mesa = app(ControllerMesa::class);

        (new \ReflectionMethod(ControllerMesa::class, 'crearComanda'))->invoke($mesa, $id, [
            'token_idempotencia' => 'token-dos-meseros', 'id_mesa' => 1, 'id_empleado' => $this->idMesero,
            'detalles' => [['num_linea' => 1, 'id_producto' => 1, 'cantidad' => 2, 'notas' => 'PEDIDO-MESERO-A']],
        ]);

        return $id;
    }

    private function ampliar(int $id, string $nota): void
    {
        (new \ReflectionMethod(ControllerMesa::class, 'ampliarComanda'))->invoke(app(ControllerMesa::class), $id, [
            ['id_producto' => 1, 'cantidad' => 1, 'notas' => $nota],
        ]);
    }

    public function test_segundo_mesero_agrega_y_cocina_sigue_viendo_lo_del_primero(): void
    {
        $id = $this->pedidoConDosRondas();
        $this->ampliar($id, 'PEDIDO-MESERO-B');

        $comanda = ComandaNoSql::where('id_pedido_sql', $id)->first();
        $this->assertSame(2, (int) $comanda->ronda_actual);
        $this->assertCount(2, $comanda->lineasPendientes(), 'La ronda 1 desapareció de cocina.');

        $this->iniciarSesionComo($this->idCocinero);
        $this->get(route('cocina.index'))->assertOk()
            ->assertSee('PEDIDO-MESERO-A')
            ->assertSee('PEDIDO-MESERO-B')
            ->assertSee('Rondas 1–2');
    }

    public function test_si_cocina_ya_termino_la_ronda_anterior_solo_muestra_la_nueva(): void
    {
        $id = $this->pedidoConDosRondas();

        $comanda = ComandaNoSql::where('id_pedido_sql', $id)->first();
        $comanda->estado = 'LISTO';
        $comanda->ronda_lista = 1;
        $comanda->save();

        $this->ampliar($id, 'PEDIDO-MESERO-B');

        $lineas = ComandaNoSql::where('id_pedido_sql', $id)->first()->lineasPendientes();
        $this->assertCount(1, $lineas);
        $this->assertSame('PEDIDO-MESERO-B', $lineas->first()['notas']);
    }

    public function test_comanda_activa_de_un_pedido_ya_facturado_deja_de_mostrarse(): void
    {
        $id = $this->crearPedido('token-actual', 'FACTURADO');
        $this->comandaVieja($id, 'token-actual', 'ABIERTO');
        $this->iniciarSesionComo($this->idCocinero);

        $this->get(route('cocina.index'))->assertOk();

        $this->assertSame('FACTURADO', ComandaNoSql::where('id_pedido_sql', $id)->first()->estado);
    }
}
