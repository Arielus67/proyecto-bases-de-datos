<?php

namespace Tests\Unit;

use App\Support\Sp;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use PDOStatement;
use Tests\TestCase;

class SentenciaConMensajes extends PDOStatement
{
    public static array $resultados = [];

    public static ?string $fallaEn = null;

    public static int $avances = 0;

    private int $indice = 0;

    protected function __construct()
    {
    }

    public function execute(?array $params = null): bool
    {
        $this->indice = 0;
        self::$avances = 0;

        return true;
    }

    public function columnCount(): int
    {
        return self::$resultados[$this->indice] === null ? 0 : 2;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return self::$resultados[$this->indice] ?? false;
    }

    public function nextRowset(): bool
    {
        self::$avances++;

        if (self::$fallaEn !== null && $this->indice + 1 === count(self::$resultados)) {
            throw new PDOException('SQLSTATE[42000]: [Microsoft][ODBC Driver 18 for SQL Server][SQL Server]' . self::$fallaEn);
        }

        if ($this->indice + 1 >= count(self::$resultados)) {
            return false;
        }

        $this->indice++;

        return true;
    }

    public function closeCursor(): bool
    {
        return true;
    }
}

class LoteSqlServerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.sqlsrv' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('sqlsrv');

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [SentenciaConMensajes::class]);
        DB::connection('sqlsrv')->setPdo($pdo);

        SentenciaConMensajes::$fallaEn = null;
    }

    public function test_salta_los_mensajes_de_backup_y_devuelve_el_select_final(): void
    {
        SentenciaConMensajes::$resultados = [null, null, (object) ['id_respaldo' => 7, 'archivo' => 'C:\\RespaldosRestaurante\\RestauranteDB_x.bak']];

        $fila = Sp::lote('SELECT 1', ['C:\\RespaldosRestaurante\\', 1]);

        $this->assertSame(7, $fila->id_respaldo);
        $this->assertSame('C:\\RespaldosRestaurante\\RestauranteDB_x.bak', $fila->archivo);
    }

    public function test_lee_todos_los_resultados_para_que_el_lote_termine(): void
    {
        SentenciaConMensajes::$resultados = [null, null, null, null];

        $this->assertNull(Sp::lote('SELECT 1'));
        $this->assertSame(4, SentenciaConMensajes::$avances);
    }

    public function test_un_error_despues_de_los_mensajes_llega_como_query_exception(): void
    {
        SentenciaConMensajes::$resultados = [null, null];
        SentenciaConMensajes::$fallaEn = 'Cannot open backup device. Operating system error 5(Access is denied.).';

        try {
            Sp::lote('SELECT 1');
            $this->fail('Debió lanzar la excepción');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Operating system error 5', $e->getMessage());
            $this->assertStringContainsString('(Connection: sqlsrv', $e->getMessage());
        }
    }
}
