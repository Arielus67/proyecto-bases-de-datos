<?php

namespace Tests\Feature;

use App\Models\Rol;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\EsquemaSqlite;
use Tests\TestCase;

class PermisosTest extends TestCase
{
    use EsquemaSqlite;

    private int $idAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBaseDeDatos();
        $this->sembrarDatosBase();
        $this->idAdmin = $this->crearEmpleado('Administrador', 'admin@prueba.test');
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

    private function crearRol(string $nombre, array $claves): int
    {
        $id = $this->bd()->table('ROL')->insertGetId(['nombre_rol' => $nombre], 'id_rol');

        foreach ($this->bd()->table('PERMISO')->whereIn('clave', $claves)->pluck('id_permiso') as $permiso) {
            $this->bd()->table('ROL_PERMISO')->insert(['id_rol' => $id, 'id_permiso' => $permiso]);
        }

        return $id;
    }

    private function empleadoConRol(string $nombreRol, array $claves, string $correo = 'custom@prueba.test'): int
    {
        $this->crearRol($nombreRol, $claves);

        return $this->crearEmpleado($nombreRol, $correo);
    }

    private function permisosDelRol(string $nombre): array
    {
        return $this->bd()->table('ROL_PERMISO as rp')
            ->join('ROL as r', 'r.id_rol', '=', 'rp.id_rol')
            ->join('PERMISO as p', 'p.id_permiso', '=', 'rp.id_permiso')
            ->where('r.nombre_rol', $nombre)
            ->orderBy('p.clave')
            ->pluck('p.clave')
            ->all();
    }

    public function test_el_catalogo_de_permisos_cubre_todas_las_rutas(): void
    {
        $catalogo = array_column($this->catalogoPermisos(), 'clave');
        $usados = [];

        foreach (Route::getRoutes() as $ruta) {
            foreach ($ruta->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'can:')) {
                    $usados[] = substr($middleware, 4);
                }
            }
        }

        $this->assertNotEmpty($usados);
        $this->assertSame([], array_values(array_diff(array_unique($usados), $catalogo)), 'Hay rutas que piden permisos que no existen en 3_datos_iniciales.sql');
        $this->assertGreaterThanOrEqual(59, count($catalogo));
    }

    public function test_todas_las_rutas_del_sistema_exigen_sesion_y_permiso(): void
    {
        $publicas = ['login', 'login.store', 'logout', 'sesion.latido', 'inicio', 'up', 'storage.local', 'storage.local.upload'];

        foreach (Route::getRoutes() as $ruta) {
            if (in_array($ruta->getName(), $publicas, true) || $ruta->uri() === 'up') {
                continue;
            }

            $middleware = $ruta->gatherMiddleware();
            $this->assertContains('auth', $middleware, 'La ruta ' . $ruta->uri() . ' no exige sesión.');
            $this->assertNotEmpty(array_filter($middleware, fn ($m) => is_string($m) && str_starts_with($m, 'can:')), 'La ruta ' . $ruta->uri() . ' no exige permiso.');
        }
    }

    public function test_un_rol_solo_con_ver_productos_no_puede_crear_editar_ni_eliminar(): void
    {
        $this->iniciarSesionComo($this->empleadoConRol('Consulta de carta', ['productos.ver']));

        $html = $this->get(route('admin.productos.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString(route('admin.productos.create'), $html);
        $this->assertStringNotContainsString('title="Editar"', $html);
        $this->assertStringNotContainsString('title="Eliminar"', $html);

        $this->get(route('admin.productos.create'))->assertForbidden();
        $this->post(route('admin.productos.store'), ['nombre_producto' => 'X'])->assertForbidden();
        $this->get(route('admin.productos.edit', 1))->assertForbidden();
        $this->put(route('admin.productos.update', 1), ['nombre_producto' => 'X'])->assertForbidden();
        $this->delete(route('admin.productos.destroy', 1))->assertForbidden();
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('admin.empleados.index'))->assertForbidden();
        $this->get(route('mesero.index'))->assertForbidden();

        $this->assertSame(3, $this->bd()->table('PRODUCTO')->count());
    }

    public function test_el_menu_lateral_muestra_solo_los_modulos_permitidos(): void
    {
        $this->bd()->table('INSUMO')->insert(['nombre_insumo' => 'Arroz', 'unidad_medida' => 'kg', 'stock_actual' => 10, 'stock_minimo' => 2, 'costo_unitario' => 900]);
        $this->iniciarSesionComo($this->empleadoConRol('Bodega', ['productos.ver', 'insumos.ver', 'insumos.editar']));

        $html = $this->get(route('admin.insumos.index'))->assertOk()->getContent();
        preg_match('#<nav class="lateral__nav">(.*?)</nav>#s', $html, $menu);

        $this->assertStringContainsString(route('admin.productos.index'), $menu[1]);
        $this->assertStringContainsString(route('admin.insumos.index'), $menu[1]);
        $this->assertStringNotContainsString('href="' . route('admin.dashboard') . '"', $menu[1]);
        $this->assertStringNotContainsString(route('admin.respaldos.index'), $menu[1]);
        $this->assertStringNotContainsString('>Control<', $menu[1]);
        $this->assertStringContainsString('title="Editar"', $html);
        $this->assertStringNotContainsString('title="Eliminar"', $html);
    }

    public function test_un_rol_personalizado_entra_a_su_primera_pantalla_permitida(): void
    {
        $this->empleadoConRol('Supervisor de pedidos', ['pedidos.ver', 'facturas.ver']);

        $this->post(route('login.store'), ['correo' => 'custom@prueba.test', 'contrasena' => 'secreto123'])
            ->assertRedirect(route('admin.pedidos.index'));
    }

    public function test_un_rol_sin_permisos_no_puede_iniciar_sesion(): void
    {
        $this->empleadoConRol('Sin acceso', []);

        $this->from(route('login'))
            ->post(route('login.store'), ['correo' => 'custom@prueba.test', 'contrasena' => 'secreto123'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('correo');

        $this->assertGuest();
    }

    public function test_crear_un_rol_con_varios_permisos(): void
    {
        $this->iniciarSesionComo($this->idAdmin);

        $this->get(route('admin.roles.create'))->assertOk()->assertSee('Ver productos')->assertSee('Restaurar la base de datos');

        $this->post(route('admin.roles.store'), [
            'nombre_rol' => 'Encargado de bodega',
            'permisos' => ['insumos.ver', 'insumos.editar', 'alertas.ver', 'alertas.atender'],
        ])->assertRedirect(route('admin.roles.index'))->assertSessionHas('success');

        $this->assertSame(['alertas.atender', 'alertas.ver', 'insumos.editar', 'insumos.ver'], $this->permisosDelRol('Encargado de bodega'));
    }

    public function test_modificar_los_permisos_de_un_rol(): void
    {
        $id = $this->crearRol('Caja nocturna', ['caja.usar']);
        $this->iniciarSesionComo($this->idAdmin);

        $this->get(route('admin.roles.edit', $id))->assertOk()->assertSee('value="caja.usar" checked', false);

        $this->put(route('admin.roles.update', $id), [
            'nombre_rol' => 'Caja nocturna',
            'permisos' => ['caja.usar', 'facturas.ver'],
        ])->assertRedirect(route('admin.roles.index'));

        $this->assertSame(['caja.usar', 'facturas.ver'], $this->permisosDelRol('Caja nocturna'));
    }

    public static function permisosInvalidos(): array
    {
        return [
            'sin permisos' => [[], 'permisos'],
            'permiso inventado' => [['productos.ver', 'sistema.todo'], 'permisos.1'],
            'permiso repetido' => [['productos.ver', 'productos.ver'], 'permisos.0'],
        ];
    }

    #[DataProvider('permisosInvalidos')]
    public function test_los_permisos_del_rol_se_validan(array $permisos, string $campo): void
    {
        $this->iniciarSesionComo($this->idAdmin);

        $this->from(route('admin.roles.create'))
            ->post(route('admin.roles.store'), ['nombre_rol' => 'Rol raro', 'permisos' => $permisos])
            ->assertSessionHasErrors($campo);

        $this->assertNull($this->bd()->table('ROL')->where('nombre_rol', 'Rol raro')->first());
    }

    public function test_no_se_puede_quitar_el_ultimo_permiso_de_administrar_roles(): void
    {
        $idRolAdmin = $this->bd()->table('ROL')->where('nombre_rol', 'Administrador')->value('id_rol');
        $this->iniciarSesionComo($this->idAdmin);

        $this->from(route('admin.roles.edit', $idRolAdmin))
            ->put(route('admin.roles.update', $idRolAdmin), ['nombre_rol' => 'Administrador', 'permisos' => ['dashboard.ver']])
            ->assertSessionHasErrors('permisos');

        $this->assertContains('roles.editar', $this->permisosDelRol('Administrador'));
    }

    public function test_un_rol_con_empleados_no_se_elimina_y_uno_vacio_si(): void
    {
        $vacio = $this->crearRol('Temporal', ['pedidos.ver']);
        $ocupado = $this->bd()->table('ROL')->where('nombre_rol', 'Administrador')->value('id_rol');
        $this->iniciarSesionComo($this->idAdmin);

        $this->delete(route('admin.roles.destroy', $ocupado))->assertSessionHas('error');
        $this->delete(route('admin.roles.destroy', $vacio))->assertSessionHas('success');

        $this->assertNotNull($this->bd()->table('ROL')->find($ocupado, ['id_rol']) ?? $this->bd()->table('ROL')->where('id_rol', $ocupado)->first());
        $this->assertNull($this->bd()->table('ROL')->where('id_rol', $vacio)->first());
    }

    public function test_quitar_un_permiso_corta_el_acceso_en_la_siguiente_peticion(): void
    {
        $id = $this->crearRol('Consulta', ['pedidos.ver', 'facturas.ver']);
        $this->iniciarSesionComo($this->crearEmpleado('Consulta', 'consulta@prueba.test'));

        $this->get(route('admin.facturas.index'))->assertOk();

        $this->bd()->table('ROL_PERMISO')->where('id_rol', $id)
            ->whereIn('id_permiso', $this->bd()->table('PERMISO')->where('clave', 'facturas.ver')->pluck('id_permiso'))
            ->delete();

        $this->iniciarSesionComo($this->bd()->table('EMPLEADO')->where('correo', 'consulta@prueba.test')->value('id_empleado'));
        $this->get(route('admin.facturas.index'))->assertForbidden();
    }

    public function test_el_puesto_operativo_sale_de_los_permisos_del_rol(): void
    {
        $this->crearRol('Salonero', ['mesero.usar', 'pedidos.ver']);
        $this->crearRol('Multiuso', ['mesero.usar', 'caja.usar']);

        $this->assertSame(Rol::MESERO, Rol::with('permisos')->where('nombre_rol', 'Salonero')->first()->puestoOperativo());
        $this->assertSame(Rol::CAJERO, Rol::with('permisos')->where('nombre_rol', 'Cajero')->first()->puestoOperativo());
        $this->assertNull(Rol::with('permisos')->where('nombre_rol', 'Multiuso')->first()->puestoOperativo());
        $this->assertNull(Rol::with('permisos')->where('nombre_rol', 'Administrador')->first()->puestoOperativo());
    }

    public function test_un_cajero_sin_supervision_no_puede_cobrar_a_nombre_de_otro(): void
    {
        $otro = $this->crearEmpleado('Cajero', 'otro@prueba.test');
        $cajero = $this->crearEmpleado('Cajero', 'cajero@prueba.test');
        $this->iniciarSesionComo($cajero);

        $respuesta = $this->postJson(route('caja.cobrar', 999), [
            'id_cajero' => $otro,
            'pagos' => [['id_metodo_pago' => 1, 'monto' => 100]],
        ]);

        $this->assertNotSame(403, $respuesta->status());
        $this->assertSame($cajero, request()->input('id_cajero'));
    }
}
