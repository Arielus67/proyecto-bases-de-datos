<?php

namespace Tests\Unit;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DeteccionDuplicadosTest extends TestCase
{
    private function controlador(): object
    {
        return new class extends Controller {
            public function probar(\Throwable $e): bool
            {
                return $this->esDuplicado($e);
            }

            public function mensaje(\Throwable $e): string
            {
                return $this->mensajeSql($e);
            }
        };
    }

    private function errorSql(string $mensaje): QueryException
    {
        return new QueryException('sqlsrv', 'EXEC sp_registrar_pedido', [], new PDOException($mensaje));
    }

    public static function mensajesDuplicados(): array
    {
        return [
            'indice unico filtrado 2601' => ["SQLSTATE[23000]: [Microsoft][ODBC Driver 18 for SQL Server][SQL Server]Cannot insert duplicate key row in object 'dbo.PEDIDO' with unique index 'UQ_PEDIDO_MesaAbierta'. The duplicate key value is (1)."],
            'restriccion unique 2627' => ["SQLSTATE[23000]: [Microsoft][ODBC Driver 18 for SQL Server][SQL Server]Violation of UNIQUE KEY constraint 'UQ__PEDIDO__1A2B'. Cannot insert duplicate key in object 'dbo.PEDIDO'. The duplicate key value is (token-1)."],
        ];
    }

    #[DataProvider('mensajesDuplicados')]
    public function test_reconoce_los_dos_errores_de_duplicado_de_sql_server(string $mensaje): void
    {
        $this->assertTrue($this->controlador()->probar($this->errorSql($mensaje)));
    }

    public function test_no_confunde_otros_errores_con_duplicados(): void
    {
        $controlador = $this->controlador();

        $this->assertFalse($controlador->probar($this->errorSql('SQLSTATE[42000]: [SQL Server]Stock insuficiente de insumos para completar el pedido.')));
        $this->assertFalse($controlador->probar(new \RuntimeException('duplicate key')));
    }

    public function test_extrae_solo_el_mensaje_de_sql_server(): void
    {
        $error = new PDOException('SQLSTATE[42000]: [Microsoft][ODBC Driver 18 for SQL Server][SQL Server]Ese cajero ya tiene un turno abierto. (Connection: sqlsrv, SQL: EXEC sp_abrir_turno)');

        $this->assertSame('Ese cajero ya tiene un turno abierto.', $this->controlador()->mensaje($error));
    }
}
