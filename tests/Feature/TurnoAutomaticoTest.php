<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\EsquemaSqlite;
use Tests\TestCase;

class TurnoAutomaticoTest extends TestCase
{
    use EsquemaSqlite;

    private int $idCajero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBaseDeDatos();
        $this->sembrarDatosBase();
        $this->idCajero = $this->crearEmpleado('Cajero', 'cajero@prueba.test');
        $this->crearEmpleado('Mesero', 'mesero@prueba.test');
    }

    protected function tearDown(): void
    {
        $this->eliminarBaseDeDatos();

        parent::tearDown();
    }

    private function turnosAbiertos(int $idEmpleado): int
    {
        return DB::connection('sqlsrv')->table('TURNO_CAJA')->where('id_empleado', $idEmpleado)->whereNull('fecha_cierre')->count();
    }

    private function salir(): void
    {
        $this->post(route('logout'));
        Auth::forgetGuards();
        $this->flushSession();
    }

    public function test_al_iniciar_sesion_el_cajero_tiene_su_turno_abierto(): void
    {
        $this->assertSame(0, $this->turnosAbiertos($this->idCajero));

        $this->post(route('login.store'), ['correo' => 'cajero@prueba.test', 'contrasena' => 'secreto123'])
            ->assertRedirect('/caja');

        $this->assertSame(1, $this->turnosAbiertos($this->idCajero));
    }

    public function test_volver_a_entrar_reutiliza_el_turno_abierto(): void
    {
        $this->post(route('login.store'), ['correo' => 'cajero@prueba.test', 'contrasena' => 'secreto123']);
        $this->salir();
        $this->post(route('login.store'), ['correo' => 'cajero@prueba.test', 'contrasena' => 'secreto123']);

        $this->assertSame(1, $this->turnosAbiertos($this->idCajero));
    }

    public function test_si_cerro_su_turno_al_volver_a_entrar_se_abre_uno_nuevo(): void
    {
        DB::connection('sqlsrv')->table('TURNO_CAJA')->insert([
            'id_empleado' => $this->idCajero, 'monto_inicial' => 0, 'fecha_apertura' => now()->subHours(8),
            'fecha_cierre' => now()->subHour(), 'monto_final' => 1000,
        ]);

        $this->post(route('login.store'), ['correo' => 'cajero@prueba.test', 'contrasena' => 'secreto123']);

        $this->assertSame(1, $this->turnosAbiertos($this->idCajero));
        $this->assertSame(2, DB::connection('sqlsrv')->table('TURNO_CAJA')->where('id_empleado', $this->idCajero)->count());
    }

    public function test_el_cajero_puede_abrir_su_turno_desde_caja(): void
    {
        $this->iniciarSesionComo($this->idCajero);

        $this->post(route('caja.turno.abrir'))->assertRedirect(route('caja.index'));

        $this->assertSame(1, $this->turnosAbiertos($this->idCajero));
    }

    public function test_un_empleado_que_no_es_cajero_no_abre_turno(): void
    {
        $this->post(route('login.store'), ['correo' => 'mesero@prueba.test', 'contrasena' => 'secreto123']);

        $this->assertSame(0, DB::connection('sqlsrv')->table('TURNO_CAJA')->count());
    }
}
