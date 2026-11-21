<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Process;
use Tests\Concerns\EsquemaSqlite;
use Tests\TestCase;

class JavascriptVistasTest extends TestCase
{
    use EsquemaSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        if (Process::run('node --version')->failed()) {
            $this->markTestSkipped('Node.js no está disponible para validar la sintaxis.');
        }

        $this->prepararBaseDeDatos();
        $this->sembrarDatosBase();
        $this->iniciarSesionComo($this->crearEmpleado('Administrador', 'admin@prueba.test'));
    }

    protected function tearDown(): void
    {
        $this->eliminarBaseDeDatos();

        parent::tearDown();
    }

    private function assertSinScriptsEnLinea(string $html, string $vista): void
    {
        preg_match_all('#<script(?![^>]*\bsrc=)(?![^>]*application/json)[^>]*>(.*?)</script>#s', $html, $bloques);

        $this->assertCount(0, $bloques[1], "La vista {$vista} todavía tiene JavaScript embebido.");
        $this->assertDoesNotMatchRegularExpression('#\son[a-z]+="#i', $html, "La vista {$vista} usa manejadores de eventos en línea.");
    }

    private function assertDatosJsonValidos(string $html, string $vista): void
    {
        preg_match_all('#<script type="application/json" id="([^"]+)">(.*?)</script>#s', $html, $bloques);

        $this->assertNotEmpty($bloques[1], "La vista {$vista} no entrega datos al JavaScript.");

        foreach ($bloques[2] as $indice => $json) {
            json_decode($json, flags: JSON_THROW_ON_ERROR);
            $this->assertTrue(true, $bloques[1][$indice]);
        }
    }

    public function test_vistas_operativas_cargan_su_javascript_desde_archivos(): void
    {
        $vistas = [
            'mesero' => [$this->get(route('mesero.index'))->assertOk()->getContent(), 'js/modulos/mesero.js', 'mesero/mesas/index.blade.php'],
            'caja' => [$this->get(route('caja.index'))->assertOk()->getContent(), 'js/modulos/caja.js', 'cajero/facturas/index.blade.php'],
            'cocina' => [view('cocina.comandas.index', ['comandas' => collect(), 'nombresProductos' => [], 'numerosMesa' => []])->render(), 'js/modulos/cocina.js', 'cocina/comandas/index.blade.php'],
        ];

        foreach ($vistas as $nombre => [$html, $script, $archivo]) {
            $fuente = file_get_contents(resource_path('views/' . $archivo));

            $this->assertSinScriptsEnLinea($fuente, $nombre);
            $this->assertStringNotContainsString('style="', $fuente, "La vista {$nombre} usa estilos en línea.");
            $this->assertDatosJsonValidos($html, $nombre);
            $this->assertStringContainsString($script, $html);
            $this->assertStringContainsString('js/sondeo.js', $html);
            $this->assertStringContainsString('js/sistema/app.js', $html);
            $this->assertLessThan(strpos($html, $script), strpos($html, 'js/sistema/app.js'), "app.js debe cargarse antes que {$script}.");
            $this->assertStringContainsString('css/sistema/plantilla.css', $html);
        }
    }

    public function test_las_vistas_usan_la_plantilla_original(): void
    {
        $html = $this->get(route('mesero.index'))->assertOk()->getContent();

        $this->assertStringContainsString('src/assets/css/theme.css', $html);
        $this->assertStringContainsString('id="main-wrapper"', $html);
        $this->assertStringContainsString('class="page-wrapper', $html);
        $this->assertStringContainsString('La Cuchara de Luison', $html);
        $this->assertStringNotContainsString('Sistema Restaurante', $html);
        $this->assertStringContainsString('id="menu-lateral"', $html);
        $this->assertStringContainsString('css/sistema/menu-lateral.css', $html);
    }

    public function test_las_tarjetas_de_cristal_solo_se_cargan_en_administracion(): void
    {
        $administracion = $this->get(route('admin.productos.index'))->assertOk()->getContent();
        $operativo = $this->get(route('mesero.index'))->assertOk()->getContent();

        $this->assertStringContainsString('css/sistema/cristal.css', $administracion);
        $this->assertStringNotContainsString('css/sistema/cristal.css', $operativo);
        $this->assertFileExists(public_path('img/fondos/pinos.jpg'));
        $this->assertStringContainsString('img/fondos/pinos.jpg', file_get_contents(public_path('css/sistema/cristal.css')));
    }

    public function test_todos_los_archivos_javascript_del_sistema_son_validos(): void
    {
        $archivos = array_merge(glob(public_path('js/sistema/*.js')), glob(public_path('js/modulos/*.js')));

        $this->assertNotEmpty($archivos);

        foreach ($archivos as $archivo) {
            $resultado = Process::run(['node', '--check', $archivo]);
            $this->assertTrue($resultado->successful(), basename($archivo) . ":\n" . $resultado->errorOutput());
        }
    }

    public function test_hojas_de_estilo_referenciadas_existen(): void
    {
        $html = $this->get(route('mesero.index'))->getContent() . $this->get(route('caja.index'))->getContent();

        preg_match_all('#href="' . preg_quote(rtrim(asset('/'), '/'), '#') . '/([^"?]+\.css)#', $html, $hojas);

        $this->assertNotEmpty($hojas[1]);

        foreach (array_unique($hojas[1]) as $hoja) {
            $this->assertFileExists(public_path($hoja));
        }
    }
}
