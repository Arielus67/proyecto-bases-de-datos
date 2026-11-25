<?php

namespace Tests\Feature;

use App\Support\SesionUnica;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\EsquemaSqlite;
use Tests\TestCase;

class SesionUnicaTest extends TestCase
{
    use EsquemaSqlite;

    private int $idMesero;

    private int $idAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBaseDeDatos();
        $this->sembrarDatosBase();
        $this->idAdmin = $this->crearEmpleado('Administrador', 'admin@prueba.test');
        $this->idMesero = $this->crearEmpleado('Mesero', 'mesero@prueba.test');
    }

    protected function tearDown(): void
    {
        $this->eliminarBaseDeDatos();

        parent::tearDown();
    }

    private function sesiones(): \Illuminate\Database\Query\Builder
    {
        return DB::connection('sqlsrv')->table('SESION_ACTIVA');
    }

    private function entrar(string $correo = 'mesero@prueba.test')
    {
        return $this->post(route('login.store'), ['correo' => $correo, 'contrasena' => 'secreto123']);
    }

    private function otroNavegador(): void
    {
        Auth::forgetGuards();
        $this->flushSession();
    }

    public function test_al_entrar_se_crea_la_fila_de_sesion_activa(): void
    {
        $this->entrar()->assertRedirect('/mesero');

        $fila = $this->sesiones()->where('id_empleado', $this->idMesero)->first();

        $this->assertNotNull($fila);
        $this->assertSame(64, strlen($fila->token));
        $this->assertSame($fila->token, session(SesionUnica::CLAVE)['token']);
    }

    public function test_no_se_puede_entrar_con_la_misma_cuenta_desde_otro_navegador(): void
    {
        $this->entrar()->assertRedirect('/mesero');
        $tokenOriginal = $this->sesiones()->value('token');

        $this->otroNavegador();

        $this->from(route('login'))->entrar()
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['correo' => SesionUnica::mensajeOcupada()]);

        $this->assertGuest();
        $this->assertSame(1, $this->sesiones()->count());
        $this->assertSame($tokenOriginal, $this->sesiones()->value('token'));
    }

    public function test_otra_cuenta_si_puede_entrar_mientras_la_primera_sigue_abierta(): void
    {
        $this->entrar()->assertRedirect('/mesero');
        $this->otroNavegador();

        $this->entrar('admin@prueba.test')->assertRedirect('/admin');

        $this->assertSame(2, $this->sesiones()->count());
    }

    public function test_al_salir_se_borra_la_fila_y_la_cuenta_queda_libre(): void
    {
        $this->entrar();
        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertSame(0, $this->sesiones()->count());

        $this->otroNavegador();
        $this->entrar()->assertRedirect('/mesero');
    }

    public function test_una_sesion_abandonada_se_libera_despues_del_tiempo_de_inactividad(): void
    {
        config(['restaurante.sesion_unica_minutos' => 5]);

        $this->entrar();
        $this->sesiones()->update(['ultima_actividad' => now()->subMinutes(6)->toDateTimeString()]);

        $this->otroNavegador();
        $this->entrar()->assertRedirect('/mesero');

        $this->assertSame(1, $this->sesiones()->count());
    }

    public function test_cada_peticion_mantiene_viva_la_sesion(): void
    {
        $this->entrar();
        $this->sesiones()->update(['ultima_actividad' => now()->subMinutes(3)->toDateTimeString()]);

        $this->getJson(route('sesion.latido'))->assertNoContent();

        $this->assertTrue(now()->subSeconds(5)->lessThanOrEqualTo($this->sesiones()->value('ultima_actividad')));
    }

    public function test_si_la_fila_desaparece_la_sesion_del_navegador_se_cierra(): void
    {
        $this->entrar();
        $this->sesiones()->delete();

        $this->get('/mesero')->assertRedirect(route('login'));
        $this->assertGuest();

        $this->get(route('login'))->assertOk()->assertSee('Tu sesión se cerró');
    }

    public function test_el_sondeo_recibe_401_cuando_la_sesion_ya_no_es_valida(): void
    {
        $this->entrar();
        $this->sesiones()->update(['token' => str_repeat('x', 64)]);

        $this->getJson(route('sesion.latido'))->assertStatus(401)->assertJsonPath('redirect', route('login'));
    }

    public function test_el_administrador_puede_cerrar_la_sesion_de_un_empleado(): void
    {
        $this->entrar();
        $this->otroNavegador();
        $this->entrar('admin@prueba.test');

        $this->get(route('admin.empleados.index'))->assertOk()->assertSee('Abierta');

        $this->delete(route('admin.empleados.cerrar-sesion', $this->idMesero))
            ->assertRedirect(route('admin.empleados.index'))
            ->assertSessionHas('success');

        $this->assertSame(0, $this->sesiones()->where('id_empleado', $this->idMesero)->count());
        $this->assertSame(1, $this->sesiones()->where('id_empleado', $this->idAdmin)->count());
    }

    public function test_el_administrador_no_cierra_su_propia_sesion_desde_empleados(): void
    {
        $this->entrar('admin@prueba.test');

        $this->from(route('admin.empleados.index'))
            ->delete(route('admin.empleados.cerrar-sesion', $this->idAdmin))
            ->assertSessionHas('error');

        $this->assertSame(1, $this->sesiones()->where('id_empleado', $this->idAdmin)->count());
    }

    public function test_los_scripts_sql_definen_la_tabla_y_los_procedimientos(): void
    {
        $archivoEstructura = base_path('database/sql/1_RestauranteDB_estructura.sql');
        $archivoProgramacion = base_path('database/sql/2_RestauranteDB_programacion.sql');

        if (! is_file($archivoEstructura) || ! is_file($archivoProgramacion)) {
            $this->markTestSkipped('Los scripts SQL no están junto al proyecto.');
        }

        $estructura = file_get_contents($archivoEstructura);
        $programacion = file_get_contents($archivoProgramacion);

        $this->assertStringContainsString('CREATE TABLE SESION_ACTIVA', $estructura);
        $this->assertMatchesRegularExpression('/id_empleado\s+INT UNIQUE NOT NULL/', $estructura);

        foreach (['sp_sesion_abrir', 'sp_sesion_validar', 'sp_sesion_cerrar'] as $procedimiento) {
            $this->assertStringContainsString('CREATE PROCEDURE ' . $procedimiento, $programacion);
        }
    }
}
