<?php

namespace Tests\Unit;

use App\Models\Permiso;
use App\Models\Rol;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El puesto operativo de un rol (mesero, cocinero, cajero) se decide por sus permisos,
 * nunca por el nombre: un rol llamado "Administrador de cocina" no obtiene nada por su nombre.
 */
class RolTipoTest extends TestCase
{
    public static function casos(): array
    {
        return [
            'solo comandas es mesero' => ['Salonero', ['mesero.usar'], Rol::MESERO],
            'solo cocina es cocinero' => ['Cocinero', ['cocina.usar'], Rol::COCINERO],
            'solo caja es cajero' => ['cajero', ['caja.usar'], Rol::CAJERO],
            'permisos administrativos no son un puesto operativo' => ['Administrador', ['empleados.gestionar', 'productos.gestionar'], null],
            'mezcla de puestos no es un puesto único' => ['Multiuso', ['mesero.usar', 'caja.usar'], null],
            'el nombre no da puesto sin permisos' => ['Administrador de cocina', [], null],
            'puesto operativo junto a permisos administrativos' => ['Jefe de caja', ['caja.usar', 'reportes.ver'], Rol::CAJERO],
        ];
    }

    #[DataProvider('casos')]
    public function test_el_puesto_operativo_se_decide_por_los_permisos(string $nombre, array $claves, ?string $esperado): void
    {
        $rol = (new Rol)->forceFill(['nombre_rol' => $nombre]);
        $rol->setRelation('permisos', collect($claves)->map(fn ($clave) => (new Permiso)->forceFill(['clave' => $clave])));

        $this->assertSame($esperado, $rol->puestoOperativo());
    }
}
