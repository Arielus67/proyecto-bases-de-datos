<?php

namespace Tests\Feature;

use App\Support\Sp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\Concerns\EsquemaSqlite;
use Tests\TestCase;

/**
 * Fotos de productos: el archivo va a public/<carpeta> y su ruta a PRODUCTO.foto.
 */
class ImagenesProductoTest extends TestCase
{
    use EsquemaSqlite;

    private string $carpeta = 'img/productos-pruebas';
    private int $admin;
    private int $mesero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBaseDeDatos();
        $this->sembrarDatosBase();

        config(['restaurante.carpeta_fotos' => $this->carpeta]);
        File::deleteDirectory(public_path($this->carpeta));

        $this->admin = $this->crearEmpleado('Administrador', 'admin@prueba.test');
        $this->mesero = $this->crearEmpleado('Mesero', 'mesero@prueba.test');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(public_path($this->carpeta));
        $this->eliminarBaseDeDatos();

        parent::tearDown();
    }

    private function producto(string $nombre): object
    {
        return DB::connection('sqlsrv')->table('PRODUCTO')->where('nombre_producto', $nombre)->first();
    }

    private function datosProducto(object $producto, array $extra = []): array
    {
        return array_merge([
            'nombre_producto' => $producto->nombre_producto,
            'descripcion' => $producto->descripcion,
            'precio' => $producto->precio,
            'id_categoria' => $producto->id_categoria,
            'id_estacion' => $producto->id_estacion,
            'activo' => 1,
        ], $extra);
    }

    private function requiereSqlServer(): void
    {
        if (! Sp::disponibles()) {
            $this->markTestSkipped('Guardar productos usa procedimientos almacenados: requiere SQL Server.');
        }
    }

    public function test_el_menu_muestra_la_foto_guardada_en_la_columna_foto(): void
    {
        $this->iniciarSesionComo($this->mesero);

        $this->get(route('mesero.mesas.productos', 1))
            ->assertOk()
            ->assertSee('img/productos/predeterminadas/refresco-natural.svg', false)   // tiene foto
            ->assertSee('menu-producto__imagen--vacia', false);                          // los demás no
    }

    #[RequiresPhpExtension('gd')]
    public function test_rechaza_archivos_que_no_son_imagen_o_demasiado_grandes(): void
    {
        $producto = $this->producto('Casado');
        $this->iniciarSesionComo($this->admin);

        $this->put(route('admin.productos.update', $producto->id_producto), $this->datosProducto($producto, [
            'imagen' => UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf'),
        ]))->assertSessionHasErrors('imagen');

        $this->put(route('admin.productos.update', $producto->id_producto), $this->datosProducto($producto, [
            'imagen' => UploadedFile::fake()->image('grande.jpg', 800, 600)->size(3000),
        ]))->assertSessionHasErrors('imagen');

        $this->assertDirectoryDoesNotExist(public_path($this->carpeta));
    }

    public function test_subir_reemplazar_y_quitar_la_foto(): void
    {
        $this->requiereSqlServer();

        $producto = $this->producto('Pargo frito');
        $this->iniciarSesionComo($this->admin);

        // Subir: se guarda el archivo y su ruta en foto
        $this->put(route('admin.productos.update', $producto->id_producto), $this->datosProducto($producto, [
            'imagen' => UploadedFile::fake()->image('pargo.jpg', 800, 600),
        ]))->assertSessionHasNoErrors();

        $primera = $this->producto('Pargo frito')->foto;
        $this->assertStringStartsWith($this->carpeta . '/', $primera);
        $this->assertFileExists(public_path($primera));

        // Reemplazar: el archivo anterior se borra
        $this->put(route('admin.productos.update', $producto->id_producto), $this->datosProducto($producto, [
            'imagen' => UploadedFile::fake()->image('pargo2.png', 800, 600),
        ]));

        $segunda = $this->producto('Pargo frito')->foto;
        $this->assertFileDoesNotExist(public_path($primera));
        $this->assertFileExists(public_path($segunda));

        // Quitar: foto queda NULL y se borra el archivo
        $this->put(route('admin.productos.update', $producto->id_producto), $this->datosProducto($producto, ['quitar_imagen' => 1]));

        $this->assertNull($this->producto('Pargo frito')->foto);
        $this->assertFileDoesNotExist(public_path($segunda));
    }

    public function test_crear_producto_con_foto_y_eliminarlo_borra_el_archivo(): void
    {
        $this->requiereSqlServer();

        $base = $this->producto('Casado');
        $this->iniciarSesionComo($this->admin);

        $this->post(route('admin.productos.store'), $this->datosProducto($base, [
            'nombre_producto' => 'Olla de carne',
            'imagen' => UploadedFile::fake()->image('olla.jpg', 800, 600),
        ]))->assertRedirect(route('admin.productos.index'));

        $nuevo = $this->producto('Olla de carne');
        $this->assertFileExists(public_path($nuevo->foto));

        $this->delete(route('admin.productos.destroy', $nuevo->id_producto))->assertRedirect(route('admin.productos.index'));
        $this->assertFileDoesNotExist(public_path($nuevo->foto));
    }
}
