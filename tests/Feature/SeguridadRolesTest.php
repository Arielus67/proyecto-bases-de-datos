<?php

namespace Tests\Feature;

use App\Models\Empleado;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\EsquemaSqlite;
use Tests\TestCase;

class SeguridadRolesTest extends TestCase
{
    use EsquemaSqlite;

    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBaseDeDatos();
        $this->sembrarDatosBase();

        $this->ids = [
            'Administrador' => $this->crearEmpleado('Administrador', 'admin@prueba.test'),
            'Mesero' => $this->crearEmpleado('Mesero', 'mesero@prueba.test'),
            'Cocinero' => $this->crearEmpleado('Cocinero', 'cocina@prueba.test'),
            'Cajero' => $this->crearEmpleado('Cajero', 'cajero@prueba.test'),
        ];
    }

    protected function tearDown(): void
    {
        $this->eliminarBaseDeDatos();

        parent::tearDown();
    }

    public static function rutasProtegidas(): array
    {
        return [
            ['get', '/'],
            ['get', '/admin'],
            ['get', '/admin/empleados'],
            ['get', '/admin/turnos/create'],
            ['get', '/admin/respaldos'],
            ['get', '/mesero'],
            ['get', '/mesero/mesas/1/productos'],
            ['get', '/cocina'],
            ['get', '/caja'],
            ['get', '/caja/facturas'],
        ];
    }

    #[DataProvider('rutasProtegidas')]
    public function test_visitante_sin_sesion_es_enviado_al_login(string $metodo, string $url): void
    {
        $this->{$metodo}($url)->assertRedirect(route('login'));
    }

    public function test_peticion_ajax_sin_sesion_responde_401(): void
    {
        $this->postJson('/mesero/guardar-pedido', [])->assertUnauthorized();
        $this->postJson('/caja/cobrar/1', [])->assertUnauthorized();
    }

    public function test_pagina_de_login_se_muestra(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Iniciar sesión');
    }

    public static function inicioPorRol(): array
    {
        return [
            ['admin@prueba.test', '/admin'],
            ['mesero@prueba.test', '/mesero'],
            ['cocina@prueba.test', '/cocina'],
            ['cajero@prueba.test', '/caja'],
        ];
    }

    #[DataProvider('inicioPorRol')]
    public function test_cada_rol_inicia_sesion_y_va_a_su_modulo(string $correo, string $destino): void
    {
        $this->post(route('login.store'), ['correo' => $correo, 'contrasena' => 'secreto123'])
            ->assertRedirect($destino);

        $this->assertAuthenticated();
    }

    public function test_contrasena_incorrecta_no_inicia_sesion(): void
    {
        $this->from(route('login'))
            ->post(route('login.store'), ['correo' => 'mesero@prueba.test', 'contrasena' => 'otra-clave'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('correo');

        $this->assertGuest();
    }

    public function test_empleado_inactivo_no_inicia_sesion(): void
    {
        $this->crearEmpleado('Mesero', 'inactivo@prueba.test', 'secreto123', false);

        $this->post(route('login.store'), ['correo' => 'inactivo@prueba.test', 'contrasena' => 'secreto123'])
            ->assertSessionHasErrors('correo');

        $this->assertGuest();
    }

    public function test_empleado_desactivado_con_sesion_abierta_es_expulsado(): void
    {
        $this->iniciarSesionComo($this->ids['Mesero']);

        DB::connection('sqlsrv')->table('EMPLEADO')->where('id_empleado', $this->ids['Mesero'])->update(['activo' => 0]);
        $this->actingAs(Empleado::find($this->ids['Mesero']));

        $this->get('/mesero')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_contrasena_se_valida_contra_contrasena_hash_y_no_se_expone(): void
    {
        $empleado = Empleado::find($this->ids['Cajero']);

        $this->assertSame('contrasena_hash', $empleado->getAuthPasswordName());
        $this->assertArrayNotHasKey('contrasena_hash', $empleado->toArray());
    }

    public static function accesosDenegados(): array
    {
        return [
            ['Mesero', 'get', '/admin'],
            ['Mesero', 'get', '/admin/empleados'],
            ['Mesero', 'get', '/cocina'],
            ['Mesero', 'get', '/caja'],
            ['Cocinero', 'get', '/mesero'],
            ['Cocinero', 'get', '/caja'],
            ['Cocinero', 'get', '/admin/pedidos'],
            ['Cajero', 'get', '/admin'],
            ['Cajero', 'get', '/admin/turnos'],
            ['Cajero', 'get', '/admin/turnos/create'],
            ['Cajero', 'post', '/admin/turnos'],
            ['Cajero', 'get', '/admin/respaldos'],
            ['Cajero', 'post', '/admin/respaldos'],
            ['Cajero', 'get', '/mesero'],
            ['Cajero', 'get', '/cocina'],
        ];
    }

    #[DataProvider('accesosDenegados')]
    public function test_acceso_directo_por_url_a_modulo_ajeno_responde_403(string $rol, string $metodo, string $url): void
    {
        $this->iniciarSesionComo($this->ids[$rol]);

        $this->{$metodo}($url, ...($metodo === 'post' ? [['id_empleado' => $this->ids['Cajero'], 'monto_inicial' => 5000]] : []))
            ->assertForbidden();
    }

    public function test_cajero_no_puede_abrir_turno_con_peticion_manipulada(): void
    {
        $this->iniciarSesionComo($this->ids['Cajero']);

        $this->post('/admin/turnos', ['id_empleado' => $this->ids['Cajero'], 'monto_inicial' => 5000])->assertForbidden();

        $this->assertSame(0, DB::connection('sqlsrv')->table('TURNO_CAJA')->count());
    }

    public function test_administrador_accede_a_los_modulos_operativos(): void
    {
        $this->iniciarSesionComo($this->ids['Administrador']);

        $this->get('/admin')->assertOk();
        $this->get('/mesero')->assertOk();
        $this->get('/caja')->assertOk();
    }

    public function test_cada_rol_operativo_accede_a_su_modulo(): void
    {
        $this->iniciarSesionComo($this->ids['Mesero']);
        $this->get('/mesero')->assertOk();

        $this->iniciarSesionComo($this->ids['Cajero']);
        $this->get('/caja')->assertOk();
    }

    public function test_cerrar_sesion(): void
    {
        $this->iniciarSesionComo($this->ids['Mesero']);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_usuario_con_sesion_no_ve_el_login(): void
    {
        $this->iniciarSesionComo($this->ids['Cajero']);

        $this->get(route('login'))->assertRedirect(route('inicio'));
    }
}
