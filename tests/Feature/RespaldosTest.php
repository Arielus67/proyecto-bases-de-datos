<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\EsquemaSqlite;
use Tests\TestCase;

class RespaldosTest extends TestCase
{
    use EsquemaSqlite;

    private int $idAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBaseDeDatos();
        $this->sembrarDatosBase();
        $this->idAdmin = $this->crearEmpleado('Administrador', 'admin@prueba.test');
        $this->iniciarSesionComo($this->idAdmin);
    }

    protected function tearDown(): void
    {
        $this->eliminarBaseDeDatos();

        parent::tearDown();
    }

    private function crearRespaldo(string $archivo, bool $verificado = true): int
    {
        return DB::connection('sqlsrv')->table('RESPALDO_BD')->insertGetId([
            'nombre_archivo' => $archivo,
            'ruta_completa' => 'C:\\RespaldosRestaurante\\' . $archivo,
            'fecha_hora' => now()->toDateTimeString(),
            'id_empleado' => $this->idAdmin,
            'verificado' => $verificado ? 1 : 0,
            'resultado' => 'Prueba',
        ], 'id_respaldo');
    }

    public function test_la_lista_muestra_el_boton_de_restaurar(): void
    {
        $verificado = $this->crearRespaldo('RestauranteDB_bueno.bak');
        $this->crearRespaldo('RestauranteDB_danado.bak', false);

        $html = $this->get(route('admin.respaldos.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Restaurar base de datos', $html);
        $this->assertStringContainsString(route('admin.respaldos.restaurar', ['respaldo' => $verificado]), $html);
        $this->assertSame(1, substr_count($html, 'title="Restaurar este respaldo"'));
    }

    public function test_la_lista_indica_la_carpeta_del_env(): void
    {
        config(['restaurante.respaldos.ruta' => 'C:\\RespaldosRestaurante\\']);

        $this->get(route('admin.respaldos.index'))
            ->assertOk()
            ->assertSee('C:\\RespaldosRestaurante\\')
            ->assertSee('Tomada de DB_BACKUP_PATH');
    }

    public function test_sin_carpeta_configurada_no_intenta_respaldar(): void
    {
        config(['restaurante.respaldos.ruta' => null]);

        $this->from(route('admin.respaldos.index'))
            ->post(route('admin.respaldos.store'))
            ->assertRedirect(route('admin.respaldos.index'))
            ->assertSessionHas('error', fn ($mensaje) => str_contains($mensaje, 'DB_BACKUP_PATH'));
    }

    public function test_sin_respaldos_verificados_no_se_puede_restaurar(): void
    {
        $this->crearRespaldo('RestauranteDB_danado.bak', false);

        $this->get(route('admin.respaldos.restaurar'))
            ->assertRedirect(route('admin.respaldos.index'))
            ->assertSessionHas('error');
    }

    public function test_la_pantalla_de_restauracion_preselecciona_el_respaldo(): void
    {
        $this->crearRespaldo('RestauranteDB_viejo.bak');
        $elegido = $this->crearRespaldo('RestauranteDB_elegido.bak');

        $this->get(route('admin.respaldos.restaurar', ['respaldo' => $elegido]))
            ->assertOk()
            ->assertSee('RestauranteDB_elegido.bak')
            ->assertSee('value="' . $elegido . '" selected', false)
            ->assertSee('reemplaza todos los datos actuales');
    }

    public function test_restaurar_pide_la_contrasena_correcta(): void
    {
        $id = $this->crearRespaldo('RestauranteDB_bueno.bak');

        $this->from(route('admin.respaldos.restaurar'))
            ->post(route('admin.respaldos.restaurar.ejecutar'), ['id_respaldo' => $id, 'contrasena' => 'otra', 'confirmo' => '1'])
            ->assertSessionHasErrors('contrasena');
    }

    public function test_restaurar_pide_confirmar_la_casilla(): void
    {
        $id = $this->crearRespaldo('RestauranteDB_bueno.bak');

        $this->from(route('admin.respaldos.restaurar'))
            ->post(route('admin.respaldos.restaurar.ejecutar'), ['id_respaldo' => $id, 'contrasena' => 'secreto123'])
            ->assertSessionHasErrors('confirmo');
    }

    public function test_no_se_restaura_un_respaldo_sin_verificar(): void
    {
        $id = $this->crearRespaldo('RestauranteDB_danado.bak', false);

        $this->from(route('admin.respaldos.restaurar'))
            ->post(route('admin.respaldos.restaurar.ejecutar'), ['id_respaldo' => $id, 'contrasena' => 'secreto123', 'confirmo' => '1'])
            ->assertSessionHas('error', 'Ese respaldo no existe o no está verificado.');
    }

    public function test_fuera_de_sql_server_no_toca_la_base(): void
    {
        $id = $this->crearRespaldo('RestauranteDB_bueno.bak');

        $this->from(route('admin.respaldos.restaurar'))
            ->post(route('admin.respaldos.restaurar.ejecutar'), ['id_respaldo' => $id, 'contrasena' => 'secreto123', 'confirmo' => '1'])
            ->assertSessionHas('error', 'La restauración solo está disponible con SQL Server.');

        $this->assertSame(1, DB::connection('sqlsrv')->table('RESPALDO_BD')->count());
    }

    public function test_un_mesero_no_puede_restaurar(): void
    {
        $id = $this->crearRespaldo('RestauranteDB_bueno.bak');
        $this->iniciarSesionComo($this->crearEmpleado('Mesero', 'mesero@prueba.test'));

        $this->get(route('admin.respaldos.restaurar'))->assertForbidden();
        $this->post(route('admin.respaldos.restaurar.ejecutar'), ['id_respaldo' => $id, 'contrasena' => 'secreto123', 'confirmo' => '1'])->assertForbidden();
    }

    public function test_los_scripts_sql_crean_la_carpeta_y_restauran_desde_master(): void
    {
        $archivo = base_path('database/sql/2_RestauranteDB_programacion.sql');

        if (! is_file($archivo)) {
            $this->markTestSkipped('Los scripts SQL no están junto al proyecto.');
        }

        $sql = file_get_contents($archivo);

        $this->assertStringContainsString('master.dbo.xp_create_subdir', $sql);
        $this->assertMatchesRegularExpression('/USE master;?\s+GO\s+CREATE OR ALTER PROCEDURE dbo\.sp_restaurar_restaurante/', $sql);
        $this->assertStringContainsString('WITH REPLACE, CHECKSUM, RECOVERY', $sql);
        $this->assertMatchesRegularExpression('/USE RestauranteDB;?\s+GO\s+PRINT/', $sql);
        $this->assertStringContainsString('DB_BACKUP_PATH=', file_get_contents(base_path('.env.example')));
    }
}
