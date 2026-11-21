<?php

namespace Tests\Unit;

use App\Models\Rol;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RolTipoTest extends TestCase
{
    public static function nombres(): array
    {
        return [
            'administrador exacto' => ['Administrador', Rol::ADMINISTRADOR],
            'mayúsculas y espacios' => ['  MESERO ', Rol::MESERO],
            'cocinero' => ['Cocinero', Rol::COCINERO],
            'cajero' => ['cajero', Rol::CAJERO],
            'alias admin' => ['Admin', Rol::ADMINISTRADOR],
            'rol que solo contiene una palabra clave no es administrador' => ['Administrador de cocina', null],
            'rol desconocido' => ['Gerente', null],
            'vacío' => ['', null],
        ];
    }

    #[DataProvider('nombres')]
    public function test_el_tipo_de_rol_se_decide_por_nombre_exacto(string $nombre, ?string $esperado): void
    {
        $this->assertSame($esperado, Rol::tipoDeNombre($nombre));
    }
}
