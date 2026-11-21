<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use PDOException;

class PruebasSqlServer extends Command
{
    protected $signature = 'restaurante:pruebas-sqlserver
        {--base=RestauranteDB_Pruebas : Base de datos de pruebas (nunca la principal)}
        {--carpeta= : Carpeta del servidor SQL Server para los respaldos de prueba}
        {--usuarios=5,10,25,50,100 : Niveles de usuarios simultáneos para la prueba de estrés}
        {--segundos=15 : Duración de cada nivel de estrés}
        {--solo= : Ejecuta solo algunas fases separadas por coma: preparar,respaldos,concurrencia,aislamiento,estres,fallos}
        {--trabajador= : Uso interno}
        {--datos= : Uso interno}';

    protected $description = 'Pruebas reales de concurrencia, aislamiento, estrés, respaldos y fallos sobre una COPIA de la base de datos en SQL Server';

    private const PRUEBAS = 'sqlsrv_pruebas';
    private const MASTER = 'sqlsrv_master_pruebas';

    private array $resultados = [];
    private array $estres = [];
    private string $base;
    private string $principal;

    public function handle(): int
    {
        $this->base = (string) $this->option('base');
        $this->principal = (string) config('database.connections.sqlsrv.database');

        if (! preg_match('/^[A-Za-z0-9_]+$/', $this->base) || strcasecmp($this->base, $this->principal) === 0) {
            $this->error('La base de pruebas debe tener un nombre distinto de la base principal (' . $this->principal . ').');

            return self::FAILURE;
        }

        if (config('database.connections.sqlsrv.driver') !== 'sqlsrv') {
            $this->error('Este comando necesita SQL Server.');

            return self::FAILURE;
        }

        $this->configurarConexiones();

        try {
            DB::connection(self::MASTER)->getPdo();
        } catch (\Throwable $e) {
            $this->error('No se pudo conectar a SQL Server: ' . $this->mensaje($e));

            return self::FAILURE;
        }

        if ($this->option('trabajador')) {
            return $this->trabajar((string) $this->option('trabajador'), json_decode(base64_decode((string) $this->option('datos')), true) ?: []);
        }

        $fases = array_filter(explode(',', (string) ($this->option('solo') ?: 'preparar,respaldos,concurrencia,aislamiento,estres,fallos')));
        $inicio = microtime(true);

        foreach ($fases as $fase) {
            $this->newLine();
            $this->info('== ' . mb_strtoupper($fase) . ' ==');

            try {
                match ($fase) {
                    'preparar' => $this->preparar(),
                    'respaldos' => $this->probarRespaldos(),
                    'concurrencia' => $this->probarConcurrencia(),
                    'aislamiento' => $this->probarAislamiento(),
                    'estres' => $this->probarEstres(),
                    'fallos' => $this->probarFallos(),
                    default => $this->warn('Fase desconocida: ' . $fase),
                };
            } catch (\Throwable $e) {
                $this->anotar($fase, 'La fase terminó sin errores inesperados', 'sin excepción', $this->mensaje($e), false);
            }
        }

        $ruta = $this->escribirInforme(microtime(true) - $inicio);
        $aprobadas = count(array_filter($this->resultados, fn ($r) => $r['ok'] === true));
        $this->newLine();
        $this->info("Pruebas aprobadas: {$aprobadas} de " . count($this->resultados));
        $this->info('Informe: ' . $ruta);

        return self::SUCCESS;
    }

    private function configurarConexiones(): void
    {
        $base = config('database.connections.sqlsrv');
        config([
            'database.connections.' . self::PRUEBAS => array_merge($base, ['database' => $this->base, 'multiple_active_result_sets' => false]),
            'database.connections.' . self::MASTER => array_merge($base, ['database' => 'master', 'multiple_active_result_sets' => false]),
        ]);
    }

    private function preparar(): void
    {
        $carpeta = $this->carpeta();
        $copia = $carpeta . 'copia_para_pruebas_' . date('Ymd_His') . '.bak';

        $this->lote(self::MASTER, 'BACKUP DATABASE ' . $this->nombre($this->principal) . ' TO DISK = ? WITH COPY_ONLY, INIT, CHECKSUM', [$copia]);
        $this->anotar('preparar', 'Respaldo COPY_ONLY de la base principal (no altera su cadena de respaldos)', 'archivo creado', $copia, true);

        $this->restaurarComo($copia, $this->base);
        $this->anotar('preparar', 'Copia restaurada como base independiente', $this->base, 'restaurada', true);

        foreach (['1_RestauranteDB_estructura.sql', '2_RestauranteDB_programacion.sql', '3_datos_iniciales.sql'] as $script) {
            $errores = $this->ejecutarScript(base_path('database/sql/' . $script));
            $this->anotar('preparar', "Script {$script} se ejecuta sin errores en SQL Server", '0 errores', $errores ? implode(' | ', array_slice($errores, 0, 3)) : '0 errores', $errores === []);
        }

        $this->sembrarDatosDePrueba();
        $conteo = $this->valor(self::PRUEBAS, 'SELECT COUNT(*) FROM dbo.PERMISO');
        $this->anotar('preparar', 'Catálogo de permisos cargado', '>= 59', (string) $conteo, $conteo >= 59);
    }

    private function carpeta(): string
    {
        $carpeta = $this->option('carpeta') ?: config('restaurante.respaldos.ruta')
            ?: $this->valor(self::MASTER, "SELECT CAST(SERVERPROPERTY('InstanceDefaultBackupPath') AS NVARCHAR(400))");

        return rtrim((string) $carpeta, '\\/') . '\\';
    }

    private function restaurarComo(string $archivo, string $destino): void
    {
        $archivos = $this->filas(self::MASTER, 'RESTORE FILELISTONLY FROM DISK = ?', [$archivo]);
        $datos = rtrim((string) $this->valor(self::MASTER, "SELECT CAST(SERVERPROPERTY('InstanceDefaultDataPath') AS NVARCHAR(400))"), '\\') . '\\';
        $registro = rtrim((string) $this->valor(self::MASTER, "SELECT CAST(SERVERPROPERTY('InstanceDefaultLogPath') AS NVARCHAR(400))"), '\\') . '\\';
        $mover = [];

        foreach ($archivos as $i => $f) {
            $esRegistro = ($f['Type'] ?? '') === 'L';
            $mover[] = 'MOVE ' . $this->literal($f['LogicalName']) . ' TO ' . $this->literal(($esRegistro ? $registro : $datos) . $destino . '_' . $i . ($esRegistro ? '.ldf' : '.mdf'));
        }

        if ($this->valor(self::MASTER, 'SELECT DB_ID(?)', [$destino]) !== null) {
            $this->lote(self::MASTER, 'ALTER DATABASE ' . $this->nombre($destino) . ' SET SINGLE_USER WITH ROLLBACK IMMEDIATE');
        }

        $this->lote(self::MASTER, 'RESTORE DATABASE ' . $this->nombre($destino) . ' FROM DISK = ? WITH REPLACE, RECOVERY, ' . implode(', ', $mover), [$archivo]);
        $this->lote(self::MASTER, 'ALTER DATABASE ' . $this->nombre($destino) . ' SET MULTI_USER');
        DB::purge(self::PRUEBAS);
    }

    private function ejecutarScript(string $ruta): array
    {
        $texto = preg_replace('/\bRestauranteDB\b/', $this->base, str_replace("\r\n", "\n", file_get_contents($ruta)));
        $pdo = DB::connection(self::MASTER)->getPdo();
        $errores = [];

        foreach (preg_split('/^\s*GO\s*$/mi', $texto) as $lote) {
            if (trim($lote) === '') {
                continue;
            }

            try {
                $this->drenar($pdo, $lote, []);
            } catch (PDOException $e) {
                $errores[] = Str::limit($this->mensaje($e), 200);
            }
        }

        DB::purge(self::MASTER);
        DB::purge(self::PRUEBAS);

        return $errores;
    }

    private function sembrarDatosDePrueba(): void
    {
        $bd = DB::connection(self::PRUEBAS);
        $hash = password_hash(Str::random(24), PASSWORD_BCRYPT);
        $categoria = $bd->table('CATEGORIA')->min('id_categoria');
        $estacion = $bd->table('ESTACION')->min('id_estacion');

        for ($n = 1001; $n <= 1130; $n++) {
            $bd->statement('IF NOT EXISTS (SELECT 1 FROM dbo.MESA WHERE numero_mesa = ?) INSERT INTO dbo.MESA (numero_mesa, capacidad, zona, estado) VALUES (?, 4, ?, ?)', [$n, $n, 'Pruebas', 'DISPONIBLE']);
        }

        foreach (['Insumo de prueba' => 100000, 'Insumo limitado' => 10, 'Insumo alerta' => 12] as $nombre => $stock) {
            $bd->statement('IF NOT EXISTS (SELECT 1 FROM dbo.INSUMO WHERE nombre_insumo = ?) INSERT INTO dbo.INSUMO (nombre_insumo, unidad_medida, stock_actual, stock_minimo, costo_unitario) VALUES (?, ?, ?, ?, 1)', [$nombre, $nombre, 'unidad', $stock, $nombre === 'Insumo alerta' ? 10 : 0]);
        }

        foreach (['Producto de prueba' => 'Insumo de prueba', 'Producto limitado' => 'Insumo limitado', 'Producto alerta' => 'Insumo alerta'] as $producto => $insumo) {
            $bd->statement('IF NOT EXISTS (SELECT 1 FROM dbo.PRODUCTO WHERE nombre_producto = ?) INSERT INTO dbo.PRODUCTO (nombre_producto, precio, id_categoria, id_estacion, activo) VALUES (?, 1000, ?, ?, 1)', [$producto, $producto, $categoria, $estacion]);
            $bd->statement('IF NOT EXISTS (SELECT 1 FROM dbo.RECETA r INNER JOIN dbo.PRODUCTO p ON p.id_producto = r.id_producto WHERE p.nombre_producto = ?) INSERT INTO dbo.RECETA (id_producto, id_insumo, cantidad) SELECT p.id_producto, i.id_insumo, 1 FROM dbo.PRODUCTO p CROSS JOIN dbo.INSUMO i WHERE p.nombre_producto = ? AND i.nombre_insumo = ?', [$producto, $producto, $insumo]);
        }

        $rol = $bd->table('ROL')->where('nombre_rol', 'Pruebas automáticas')->value('id_rol');

        if (! $rol) {
            $rol = $this->valor(self::PRUEBAS, "SET NOCOUNT ON; DECLARE @id INT; EXEC dbo.sp_rol_insertar @nombre_rol = N'Pruebas automáticas', @permisos = N'[\"mesero.usar\",\"caja.usar\",\"pedidos.anular\"]', @id_rol = @id OUTPUT; SELECT @id;");
        }

        for ($i = 1; $i <= 110; $i++) {
            $correo = sprintf('prueba%03d@pruebas.local', $i);
            $id = $bd->table('EMPLEADO')->where('correo', $correo)->value('id_empleado')
                ?? $bd->table('EMPLEADO')->insertGetId(['nombre' => 'Prueba', 'apellido' => sprintf('%03d', $i), 'correo' => $correo, 'contrasena_hash' => $hash, 'id_rol' => $rol, 'activo' => 1], 'id_empleado');
            $bd->statement('IF NOT EXISTS (SELECT 1 FROM dbo.CAJERO WHERE id_empleado = ?) INSERT INTO dbo.CAJERO (id_empleado, codigo_caja) VALUES (?, ?)', [$id, $id, 'PRUEBA-' . $id]);
            $bd->statement('IF NOT EXISTS (SELECT 1 FROM dbo.TURNO_CAJA WHERE id_empleado = ? AND fecha_cierre IS NULL) INSERT INTO dbo.TURNO_CAJA (id_empleado, monto_inicial) VALUES (?, 0)', [$id, $id]);
        }
    }

    private function contexto(): array
    {
        $bd = DB::connection(self::PRUEBAS);

        return [
            'empleados' => $bd->table('EMPLEADO')->where('correo', 'like', 'prueba%@pruebas.local')->orderBy('correo')->pluck('id_empleado')->map(fn ($v) => (int) $v)->all(),
            'mesas' => $bd->table('MESA')->where('numero_mesa', '>=', 1001)->orderBy('numero_mesa')->pluck('id_mesa')->map(fn ($v) => (int) $v)->all(),
            'producto' => (int) $bd->table('PRODUCTO')->where('nombre_producto', 'Producto de prueba')->value('id_producto'),
            'limitado' => (int) $bd->table('PRODUCTO')->where('nombre_producto', 'Producto limitado')->value('id_producto'),
            'alerta' => (int) $bd->table('PRODUCTO')->where('nombre_producto', 'Producto alerta')->value('id_producto'),
            'metodo' => (int) $bd->table('METODO_PAGO')->min('id_metodo_pago'),
        ];
    }

    private function liberarMesas(): void
    {
        $bd = DB::connection(self::PRUEBAS);
        $mesas = $bd->table('MESA')->where('numero_mesa', '>=', 1001)->pluck('id_mesa');
        $bd->table('PEDIDO')->whereIn('id_mesa', $mesas)->whereIn('estado', ['ABIERTO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO'])
            ->update(['estado' => 'ANULADO', 'anulado_por' => $this->contexto()['empleados'][0], 'motivo_anulacion' => 'Limpieza de pruebas']);
        $bd->table('MESA')->whereIn('id_mesa', $mesas)->update(['estado' => 'DISPONIBLE']);
    }

    private function crearPedido(int $mesa, int $empleado, int $producto, int $cantidad = 1): ?int
    {
        return $this->valor(self::PRUEBAS, 'SET NOCOUNT ON; DECLARE @id INT; EXEC dbo.sp_registrar_pedido @token_idempotencia = ?, @id_mesa = ?, @id_empleado = ?, @observaciones = NULL, @json_lineas = ?, @nuevo_id_pedido = @id OUTPUT; SELECT @id;',
            [(string) Str::uuid(), $mesa, $empleado, json_encode([['num_linea' => 1, 'id_producto' => $producto, 'cantidad' => $cantidad]])]);
    }

    private function probarRespaldos(): void
    {
        $carpeta = $this->carpeta();
        $empleado = $this->contexto()['empleados'][0];
        $conteoAntes = $this->conteos(self::PRUEBAS);
        $sumasAntes = $this->sumas(self::PRUEBAS);

        $r = $this->medir(fn () => $this->fila(self::PRUEBAS, 'SET NOCOUNT ON; DECLARE @id INT, @archivo NVARCHAR(460); EXEC dbo.sp_respaldar_bd @ruta = ?, @id_empleado = ?, @id_respaldo = @id OUTPUT, @archivo = @archivo OUTPUT; SELECT @id AS id, @archivo AS archivo;', [$carpeta, $empleado]));
        $archivo = $r['resultado']['archivo'] ?? null;
        $existe = $archivo ? $this->valor(self::MASTER, 'EXEC master.dbo.xp_fileexist ?', [$archivo]) : 0;
        $this->anotar('respaldos', 'Crear respaldo en la carpeta configurada', 'archivo .bak verificado', $r['ok'] ? "{$archivo} en {$r['ms']} ms, existe={$existe}" : $r['error'], $r['ok'] && (int) $existe === 1);

        if ($archivo) {
            $registro = DB::connection(self::PRUEBAS)->table('RESPALDO_BD')->where('id_respaldo', $r['resultado']['id'])->first();
            $this->anotar('respaldos', 'El respaldo queda registrado en RESPALDO_BD como verificado', 'verificado = 1', 'verificado = ' . ($registro->verificado ?? 'sin fila'), (int) ($registro->verificado ?? 0) === 1);

            $restaurada = $this->base . '_Restaurada';
            $r = $this->medir(fn () => $this->restaurarComo($archivo, $restaurada));
            config(['database.connections.sqlsrv_restaurada' => array_merge(config('database.connections.' . self::PRUEBAS), ['database' => $restaurada])]);
            $iguales = $r['ok'] && $this->conteos('sqlsrv_restaurada') == $conteoAntes && $this->sumas('sqlsrv_restaurada') == $sumasAntes;
            $this->anotar('respaldos', 'Restaurar el respaldo en una base independiente y comparar todas las tablas (filas y CHECKSUM)', 'idénticas', $r['ok'] ? ($iguales ? "idénticas ({$r['ms']} ms)" : 'diferencias: ' . json_encode(array_diff_assoc($this->conteos('sqlsrv_restaurada'), $conteoAntes))) : $r['error'], $iguales);
            DB::purge('sqlsrv_restaurada');
            $this->lote(self::MASTER, 'ALTER DATABASE ' . $this->nombre($restaurada) . ' SET SINGLE_USER WITH ROLLBACK IMMEDIATE; DROP DATABASE ' . $this->nombre($restaurada));

            DB::connection(self::PRUEBAS)->table('MESA')->where('numero_mesa', 1130)->update(['zona' => 'Modificada tras el respaldo']);
            DB::purge(self::PRUEBAS);
            $r = $this->medir(fn () => $this->lote(self::MASTER, 'EXEC master.dbo.sp_restaurar_restaurante @base = ?, @archivo = ?', [$this->base, $archivo]));
            DB::purge(self::PRUEBAS);
            $zona = DB::connection(self::PRUEBAS)->table('MESA')->where('numero_mesa', 1130)->value('zona');
            $acceso = $this->valor(self::MASTER, "SELECT CAST(DATABASEPROPERTYEX(?, 'UserAccess') AS VARCHAR(20))", [$this->base]);
            $this->anotar('respaldos', 'sp_restaurar_restaurante devuelve la base de pruebas al estado del respaldo', 'cambio revertido y MULTI_USER', $r['ok'] ? "zona={$zona}, acceso={$acceso}, {$r['ms']} ms" : $r['error'], $r['ok'] && $zona === 'Pruebas' && $acceso === 'MULTI_USER');
        }

        $nueva = $carpeta . 'pruebas_carpeta_nueva_' . date('His') . '\\';
        $r = $this->medir(fn () => $this->lote(self::PRUEBAS, 'SET NOCOUNT ON; DECLARE @id INT, @archivo NVARCHAR(460); EXEC dbo.sp_respaldar_bd @ruta = ?, @id_empleado = ?, @id_respaldo = @id OUTPUT, @archivo = @archivo OUTPUT;', [$nueva, $empleado]));
        $this->anotar('respaldos', 'Carpeta que no existe: SQL Server la crea', 'respaldo correcto', $r['ok'] ? 'creada y respaldada' : $r['error'], $r['ok']);

        foreach (['Q:\\carpeta_que_no_existe\\' => 'Unidad inexistente', 'C:\\Windows\\System32\\config\\' => 'Carpeta sin permiso de escritura'] as $ruta => $caso) {
            $antes = DB::connection(self::PRUEBAS)->table('RESPALDO_BD')->count();
            $r = $this->medir(fn () => $this->lote(self::PRUEBAS, 'SET NOCOUNT ON; DECLARE @id INT, @archivo NVARCHAR(460); EXEC dbo.sp_respaldar_bd @ruta = ?, @id_empleado = ?, @id_respaldo = @id OUTPUT, @archivo = @archivo OUTPUT;', [$ruta, $empleado]));
            $fallidos = DB::connection(self::PRUEBAS)->table('RESPALDO_BD')->where('verificado', 0)->where('id_respaldo', '>', 0)->count();
            $this->anotar('respaldos', "{$caso} ({$ruta})", 'error claro, sin respaldo marcado como válido', $r['ok'] ? 'NO falló' : "error {$r['codigo']}: " . Str::limit($r['error'], 140) . " (filas nuevas en historial: " . (DB::connection(self::PRUEBAS)->table('RESPALDO_BD')->count() - $antes) . ", fallidos registrados: {$fallidos})", ! $r['ok']);
        }

        $this->anotar('respaldos', 'Disco sin espacio', 'error 3202/112 registrado', 'no ejecutada: requiere una unidad pequeña llena (por ejemplo un disco virtual VHD de 50 MB) indicada con --carpeta', null);
    }

    private function conteos(string $conexion): array
    {
        $tablas = DB::connection($conexion)->select("SELECT name FROM sys.tables WHERE is_ms_shipped = 0 ORDER BY name");
        $conteos = [];

        foreach ($tablas as $t) {
            $conteos[$t->name] = (int) DB::connection($conexion)->table($t->name)->count();
        }

        return $conteos;
    }

    private function sumas(string $conexion): array
    {
        $sumas = [];

        foreach (['PEDIDO', 'DETALLE_PEDIDO', 'FACTURA', 'PAGO', 'INSUMO', 'EMPLEADO', 'ROL_PERMISO'] as $tabla) {
            $sumas[$tabla] = $this->valor($conexion, "SELECT CHECKSUM_AGG(BINARY_CHECKSUM(*)) FROM dbo.{$tabla}");
        }

        return $sumas;
    }

    private function probarConcurrencia(): void
    {
        $c = $this->contexto();
        $this->liberarMesas();

        $mesa = $c['mesas'][0];
        $res = $this->enParalelo(20, 'registrar', fn ($i) => ['mesa' => $mesa, 'empleado' => $c['empleados'][$i], 'producto' => $c['producto'], 'token' => (string) Str::uuid()]);
        $abiertos = DB::connection(self::PRUEBAS)->table('PEDIDO')->where('id_mesa', $mesa)->whereNotIn('estado', ['FACTURADO', 'ANULADO'])->count();
        $this->anotar('concurrencia', '20 meseros crean un pedido para la MISMA mesa a la vez', '1 pedido, 19 rechazos 50007', "éxitos={$res['ok']}, códigos=" . json_encode($res['codigos']) . ", pedidos abiertos={$abiertos}", $res['ok'] === 1 && $abiertos === 1);

        $token = (string) Str::uuid();
        $res = $this->enParalelo(20, 'registrar', fn ($i) => ['mesa' => $c['mesas'][1], 'empleado' => $c['empleados'][$i], 'producto' => $c['producto'], 'token' => $token]);
        $creados = DB::connection(self::PRUEBAS)->table('PEDIDO')->where('token_idempotencia', $token)->count();
        $ids = array_unique(array_filter(array_column($res['resultados'], 'id')));
        $this->anotar('concurrencia', 'El mismo pedido enviado 20 veces (doble clic, reintentos)', '1 pedido y todos reciben el mismo id', "pedidos creados={$creados}, ids distintos devueltos=" . count($ids), $creados === 1 && count($ids) === 1);

        $pedido = $this->crearPedido($c['mesas'][2], $c['empleados'][0], $c['producto']);
        $res = $this->enParalelo(20, 'cobrar', fn ($i) => ['pedido' => $pedido, 'cajero' => $c['empleados'][$i], 'metodo' => $c['metodo']]);
        $facturas = DB::connection(self::PRUEBAS)->table('FACTURA')->where('id_pedido', $pedido)->count();
        $pagos = DB::connection(self::PRUEBAS)->table('PAGO as p')->join('FACTURA as f', 'f.id_factura', '=', 'p.id_factura')->where('f.id_pedido', $pedido)->count();
        $this->anotar('concurrencia', '20 cajas cobran el MISMO pedido a la vez', '1 factura y 1 pago', "éxitos={$res['ok']}, facturas={$facturas}, pagos={$pagos}, códigos=" . json_encode($res['codigos']), $facturas === 1 && $pagos === 1);

        DB::connection(self::PRUEBAS)->table('INSUMO')->where('nombre_insumo', 'Insumo limitado')->update(['stock_actual' => 10]);
        $res = $this->enParalelo(30, 'registrar', fn ($i) => ['mesa' => $c['mesas'][10 + $i], 'empleado' => $c['empleados'][$i], 'producto' => $c['limitado'], 'token' => (string) Str::uuid()]);
        $stock = (float) DB::connection(self::PRUEBAS)->table('INSUMO')->where('nombre_insumo', 'Insumo limitado')->value('stock_actual');
        $this->anotar('concurrencia', '30 pedidos simultáneos compiten por 10 unidades de un insumo', '10 éxitos, 20 rechazos 50002, stock final 0', "éxitos={$res['ok']}, códigos=" . json_encode($res['codigos']) . ", stock final={$stock}", $res['ok'] === 10 && abs($stock) < 0.001);

        $pedido = $this->crearPedido($c['mesas'][50], $c['empleados'][0], $c['producto']);
        $res = $this->enParalelo(20, 'agregar', fn ($i) => ['pedido' => $pedido, 'producto' => $c['producto']]);
        $lineas = DB::connection(self::PRUEBAS)->table('DETALLE_PEDIDO')->where('id_pedido', $pedido)->count();
        $distintas = DB::connection(self::PRUEBAS)->table('DETALLE_PEDIDO')->where('id_pedido', $pedido)->distinct()->count('num_linea');
        $subtotal = (float) DB::connection(self::PRUEBAS)->table('PEDIDO')->where('id_pedido', $pedido)->value('subtotal');
        $suma = (float) DB::connection(self::PRUEBAS)->table('DETALLE_PEDIDO')->where('id_pedido', $pedido)->sum(DB::raw('cantidad * precio_unitario'));
        $this->anotar('concurrencia', '20 rondas agregadas al mismo pedido a la vez (actualizaciones perdidas)', '21 líneas, números únicos, subtotal = suma', "éxitos={$res['ok']}, líneas={$lineas}, únicas={$distintas}, subtotal={$subtotal}, suma={$suma}", $lineas === 21 && $distintas === 21 && abs($subtotal - $suma) < 0.01);

        $bloqueos = 0;
        $errores = [];
        for ($ronda = 0; $ronda < 15; $ronda++) {
            $mesaRonda = $c['mesas'][60 + $ronda];
            $pedido = $this->crearPedido($mesaRonda, $c['empleados'][0], $c['producto']);
            $res = $this->enParalelo(2, fn ($i) => $i === 0 ? 'cobrar' : 'registrar', fn ($i) => $i === 0
                ? ['pedido' => $pedido, 'cajero' => $c['empleados'][1], 'metodo' => $c['metodo']]
                : ['mesa' => $mesaRonda, 'empleado' => $c['empleados'][2], 'producto' => $c['producto'], 'token' => (string) Str::uuid()]);
            $bloqueos += $res['codigos'][1205] ?? 0;
            $errores = array_merge($errores, array_keys($res['codigos']));
        }
        $this->anotar('concurrencia', '15 rondas de cobro y pedido nuevo en la misma mesa al mismo tiempo (orden de bloqueo MESA → PEDIDO)', '0 deadlocks (1205)', "deadlocks={$bloqueos}, códigos vistos=" . json_encode(array_values(array_unique($errores))), $bloqueos === 0);

        $ganadores = 0;
        for ($ronda = 0; $ronda < 10; $ronda++) {
            $pedido = $this->crearPedido($c['mesas'][80 + $ronda], $c['empleados'][0], $c['producto']);
            $res = $this->enParalelo(2, fn ($i) => $i === 0 ? 'cobrar' : 'anular', fn ($i) => ['pedido' => $pedido, 'cajero' => $c['empleados'][1], 'metodo' => $c['metodo'], 'empleado' => $c['empleados'][2]]);
            $estado = DB::connection(self::PRUEBAS)->table('PEDIDO')->where('id_pedido', $pedido)->value('estado');
            $factura = DB::connection(self::PRUEBAS)->table('FACTURA')->where('id_pedido', $pedido)->exists();
            $ganadores += ($res['ok'] === 1 && (($estado === 'FACTURADO') === $factura)) ? 1 : 0;
        }
        $this->anotar('concurrencia', '10 rondas de cobrar y anular el mismo pedido a la vez', 'en las 10 gana solo uno y el estado coincide con la factura', "{$ganadores}/10 consistentes", $ganadores === 10);

        DB::connection(self::PRUEBAS)->table('INSUMO')->where('nombre_insumo', 'Insumo alerta')->update(['stock_actual' => 12, 'stock_minimo' => 10]);
        $insumoAlerta = DB::connection(self::PRUEBAS)->table('INSUMO')->where('nombre_insumo', 'Insumo alerta')->value('id_insumo');
        DB::connection(self::PRUEBAS)->table('ALERTA_STOCK')->where('id_insumo', $insumoAlerta)->update(['atendida' => 1]);
        $this->enParalelo(5, 'registrar', fn ($i) => ['mesa' => $c['mesas'][100 + $i], 'empleado' => $c['empleados'][$i], 'producto' => $c['alerta'], 'token' => (string) Str::uuid()]);
        $alertas = DB::connection(self::PRUEBAS)->table('ALERTA_STOCK')->where('id_insumo', $insumoAlerta)->where('atendida', 0)->count();
        $this->anotar('concurrencia', '5 pedidos simultáneos bajan un insumo por debajo del mínimo', '1 sola alerta pendiente', "alertas pendientes={$alertas}", $alertas === 1);

        $antes = [DB::connection(self::PRUEBAS)->table('PEDIDO')->count(), DB::connection(self::PRUEBAS)->table('DETALLE_PEDIDO')->count(), (float) DB::connection(self::PRUEBAS)->table('INSUMO')->where('nombre_insumo', 'Insumo limitado')->value('stock_actual')];
        $r = $this->medir(fn () => $this->crearPedido($c['mesas'][120], $c['empleados'][0], $c['limitado'], 5));
        $despues = [DB::connection(self::PRUEBAS)->table('PEDIDO')->count(), DB::connection(self::PRUEBAS)->table('DETALLE_PEDIDO')->count(), (float) DB::connection(self::PRUEBAS)->table('INSUMO')->where('nombre_insumo', 'Insumo limitado')->value('stock_actual')];
        $this->anotar('concurrencia', 'Error a mitad de la transacción (stock insuficiente): todo se revierte', 'sin pedidos, líneas ni stock modificados', 'código ' . ($r['codigo'] ?? 'ninguno') . ', antes=' . json_encode($antes) . ', después=' . json_encode($despues), ! $r['ok'] && $antes === $despues);

        $this->liberarMesas();
    }

    private function enParalelo(int $cantidad, string|\Closure $escenario, \Closure $datos): array
    {
        $inicio = microtime(true) + 2 + $cantidad * 0.05;
        $procesos = [];

        for ($i = 0; $i < $cantidad; $i++) {
            $nombre = is_string($escenario) ? $escenario : $escenario($i);
            $carga = base64_encode(json_encode($datos($i) + ['inicio' => $inicio]));
            $procesos[] = $this->lanzar($nombre, $carga);
        }

        $resultados = [];

        foreach ($procesos as [$proceso, $tuberias]) {
            $salida = stream_get_contents($tuberias[1]);
            fclose($tuberias[1]);
            fclose($tuberias[2]);
            proc_close($proceso);
            $linea = collect(explode("\n", trim($salida)))->last();
            $resultados[] = json_decode((string) $linea, true) ?: ['ok' => false, 'codigo' => 'sin_respuesta', 'error' => Str::limit($salida, 200)];
        }

        $codigos = [];
        foreach ($resultados as $r) {
            if (! ($r['ok'] ?? true)) {
                $codigos[$r['codigo']] = ($codigos[$r['codigo']] ?? 0) + 1;
            }
        }

        return ['ok' => count(array_filter($resultados, fn ($r) => $r['ok'] ?? true)), 'codigos' => $codigos, 'resultados' => $resultados];
    }

    private function lanzar(string $escenario, string $carga): array
    {
        $comando = [PHP_BINARY, base_path('artisan'), 'restaurante:pruebas-sqlserver', '--base=' . $this->base, '--trabajador=' . $escenario, '--datos=' . $carga];
        $proceso = proc_open($comando, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tuberias, base_path());

        return [$proceso, $tuberias];
    }

    private function trabajar(string $escenario, array $d): int
    {
        if (isset($d['inicio'])) {
            usleep((int) max(0, ($d['inicio'] - microtime(true)) * 1_000_000));
        }

        if ($escenario === 'estres') {
            echo json_encode($this->bucleDeEstres($d)) . PHP_EOL;

            return self::SUCCESS;
        }

        if ($escenario === 'interrumpido') {
            $pdo = DB::connection(self::PRUEBAS)->getPdo();
            $pdo->exec('SET XACT_ABORT ON; BEGIN TRANSACTION');
            $this->drenar($pdo, $d['sql'], $d['parametros']);
            echo json_encode(['ok' => true, 'spid' => (int) $this->escalar($pdo, 'SELECT @@SPID')]) . PHP_EOL;
            flush();
            $pdo->exec("WAITFOR DELAY '00:00:30'");
            $pdo->exec('COMMIT TRANSACTION');

            return self::SUCCESS;
        }

        echo json_encode($this->medir(fn () => $this->operacion($escenario, $d))) . PHP_EOL;

        return self::SUCCESS;
    }

    private function operacion(string $escenario, array $d): mixed
    {
        return match ($escenario) {
            'registrar' => ['id' => $this->valor(self::PRUEBAS, 'SET NOCOUNT ON; DECLARE @id INT, @r INT; EXEC @r = dbo.sp_registrar_pedido @token_idempotencia = ?, @id_mesa = ?, @id_empleado = ?, @observaciones = NULL, @json_lineas = ?, @nuevo_id_pedido = @id OUTPUT; SELECT @id;',
                [$d['token'], $d['mesa'], $d['empleado'], json_encode([['num_linea' => 1, 'id_producto' => $d['producto'], 'cantidad' => 1]])])],
            'agregar' => $this->lote(self::PRUEBAS, 'EXEC dbo.sp_agregar_items_pedido @id_pedido = ?, @json_lineas = ?', [$d['pedido'], json_encode([['id_producto' => $d['producto'], 'cantidad' => 1]])]),
            'cobrar' => $this->valor(self::PRUEBAS, 'SET NOCOUNT ON; DECLARE @n VARCHAR(50); EXEC dbo.sp_cobrar_cuenta @id_pedido = ?, @id_cliente = NULL, @id_cajero = ?, @descuento = 0, @json_pagos = ?, @numero_factura_out = @n OUTPUT; SELECT @n;',
                [$d['pedido'], $d['cajero'], json_encode([['id_metodo_pago' => $d['metodo'], 'monto' => 1000000]])]),
            'anular' => $this->lote(self::PRUEBAS, 'EXEC dbo.sp_anular_pedido @id_pedido = ?, @id_empleado = ?, @motivo = ?', [$d['pedido'], $d['empleado'], 'Prueba de concurrencia']),
            default => throw new \InvalidArgumentException('Escenario desconocido: ' . $escenario),
        };
    }

    private function bucleDeEstres(array $d): array
    {
        $fin = microtime(true) + $d['segundos'];
        $tiempos = [];
        $errores = [];
        $n = 0;

        while (microtime(true) < $fin) {
            $tipo = ['consulta', 'consulta', 'consulta', 'consulta', 'consulta', 'consulta', 'pedido', 'pedido', 'pedido', 'agregar'][$n++ % 10];
            $r = $this->medir(function () use ($tipo, $d) {
                if ($tipo === 'consulta') {
                    DB::connection(self::PRUEBAS)->select('SELECT * FROM dbo.vw_mesas_estado');
                    DB::connection(self::PRUEBAS)->select('SELECT TOP (50) * FROM dbo.vw_cola_cocina');

                    return null;
                }

                $abierto = DB::connection(self::PRUEBAS)->table('PEDIDO')->where('id_mesa', $d['mesa'])->whereIn('estado', ['ABIERTO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO'])->value('id_pedido');

                if ($tipo === 'agregar' && $abierto) {
                    return $this->operacion('agregar', ['pedido' => $abierto, 'producto' => $d['producto']]);
                }

                if ($abierto) {
                    return $this->operacion('cobrar', ['pedido' => $abierto, 'cajero' => $d['empleado'], 'metodo' => $d['metodo']]);
                }

                return $this->operacion('registrar', ['mesa' => $d['mesa'], 'empleado' => $d['empleado'], 'producto' => $d['producto'], 'token' => (string) Str::uuid()]);
            });
            $tiempos[$tipo][] = $r['ms'];

            if (! $r['ok']) {
                $errores[$r['codigo']] = ($errores[$r['codigo']] ?? 0) + 1;
            }
        }

        return ['tiempos' => $tiempos, 'errores' => $errores, 'memoria_mb' => round(memory_get_peak_usage(true) / 1048576, 1)];
    }

    private function probarAislamiento(): void
    {
        $c = $this->contexto();
        $mesa = $c['mesas'][125];
        DB::connection(self::PRUEBAS)->table('MESA')->where('id_mesa', $mesa)->update(['zona' => 'Original']);
        config(['database.connections.sqlsrv_lectora' => config('database.connections.' . self::PRUEBAS)]);
        $escritora = DB::connection(self::PRUEBAS)->getPdo();
        $lectora = DB::connection('sqlsrv_lectora')->getPdo();
        $lectora->exec('SET LOCK_TIMEOUT 2000');

        $leer = function (string $nivel, string $consulta) use ($lectora): string {
            $lectora->exec("SET TRANSACTION ISOLATION LEVEL {$nivel}");
            $t = microtime(true);
            try {
                $valor = $this->escalar($lectora, $consulta);
                return var_export($valor, true) . ' en ' . round((microtime(true) - $t) * 1000) . ' ms';
            } catch (PDOException $e) {
                return 'bloqueado: error ' . ($e->errorInfo[1] ?? '?') . ' tras ' . round((microtime(true) - $t) * 1000) . ' ms';
            }
        };

        $escritora->exec("BEGIN TRANSACTION; UPDATE dbo.MESA SET zona = 'Sin confirmar' WHERE id_mesa = {$mesa}");
        $sucia = $leer('READ UNCOMMITTED', "SELECT zona FROM dbo.MESA WHERE id_mesa = {$mesa}");
        $confirmada = $leer('READ COMMITTED', "SELECT zona FROM dbo.MESA WHERE id_mesa = {$mesa}");
        $conBloqueo = $leer('READ COMMITTED', "SELECT zona FROM dbo.MESA WITH (UPDLOCK, HOLDLOCK) WHERE id_mesa = {$mesa}");
        $escritora->exec('ROLLBACK TRANSACTION');
        $this->anotar('aislamiento', 'READ UNCOMMITTED lee datos sin confirmar (lectura sucia)', "lee 'Sin confirmar'", $sucia, str_contains($sucia, 'Sin confirmar'));
        $this->anotar('aislamiento', 'READ COMMITTED (con READ_COMMITTED_SNAPSHOT) lee la última versión confirmada sin esperar', "lee 'Original' sin bloqueo", $confirmada, str_contains($confirmada, 'Original'));
        $this->anotar('aislamiento', 'UPDLOCK, HOLDLOCK espera al escritor (por eso los procedimientos lo usan antes de modificar)', 'bloqueado (1222)', $conBloqueo, str_contains($conBloqueo, '1222'));

        $lectora->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ; BEGIN TRANSACTION');
        $this->escalar($lectora, "SELECT zona FROM dbo.MESA WHERE id_mesa = {$mesa}");
        $escritora->exec('SET LOCK_TIMEOUT 2000');
        $t = microtime(true);
        try {
            $escritora->exec("UPDATE dbo.MESA SET zona = 'Cambio' WHERE id_mesa = {$mesa}");
            $repetible = 'el cambio entró (lectura no repetible)';
        } catch (PDOException $e) {
            $repetible = 'cambio bloqueado (' . ($e->errorInfo[1] ?? '?') . ') tras ' . round((microtime(true) - $t) * 1000) . ' ms';
        }
        $lectora->exec('COMMIT TRANSACTION');
        $this->anotar('aislamiento', 'REPEATABLE READ impide que otro modifique lo leído', 'cambio bloqueado', $repetible, str_contains($repetible, 'bloqueado'));

        $lectora->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE; BEGIN TRANSACTION');
        $this->escalar($lectora, "SELECT COUNT(*) FROM dbo.MESA WHERE numero_mesa BETWEEN 1200 AND 1210");
        $t = microtime(true);
        try {
            $escritora->exec("INSERT INTO dbo.MESA (numero_mesa, capacidad, zona) VALUES (1205, 2, 'Fantasma')");
            $fantasma = 'la fila nueva entró (fantasma)';
            $escritora->exec('DELETE FROM dbo.MESA WHERE numero_mesa = 1205');
        } catch (PDOException $e) {
            $fantasma = 'inserción bloqueada (' . ($e->errorInfo[1] ?? '?') . ') tras ' . round((microtime(true) - $t) * 1000) . ' ms';
        }
        $lectora->exec('COMMIT TRANSACTION');
        $escritora->exec('SET LOCK_TIMEOUT -1');
        $this->anotar('aislamiento', 'SERIALIZABLE impide filas fantasma en el rango leído', 'inserción bloqueada', $fantasma, str_contains($fantasma, 'bloqueada'));
        DB::purge('sqlsrv_lectora');
    }

    private function probarEstres(): void
    {
        $c = $this->contexto();

        foreach (array_map('intval', explode(',', (string) $this->option('usuarios'))) as $n) {
            if ($n < 1 || $n > count($c['mesas']) || $n > count($c['empleados'])) {
                continue;
            }

            $this->liberarMesas();
            $antes = $this->contadores();
            $procedimientosAntes = $this->estadisticasProcedimientos();
            $t = microtime(true);
            $res = $this->enParalelo($n, 'estres', fn ($i) => ['mesa' => $c['mesas'][$i], 'empleado' => $c['empleados'][$i], 'producto' => $c['producto'], 'metodo' => $c['metodo'], 'segundos' => (int) $this->option('segundos')]);
            $duracion = microtime(true) - $t - 2 - $n * 0.05;
            $despues = $this->contadores();
            $todos = [];
            $porTipo = [];
            $errores = [];
            $memoria = 0;

            foreach ($res['resultados'] as $r) {
                foreach ($r['tiempos'] ?? [] as $tipo => $lista) {
                    $porTipo[$tipo] = array_merge($porTipo[$tipo] ?? [], $lista);
                    $todos = array_merge($todos, $lista);
                }
                foreach ($r['errores'] ?? [] as $codigo => $cuantos) {
                    $errores[$codigo] = ($errores[$codigo] ?? 0) + $cuantos;
                }
                if (($r['ok'] ?? true) === false) {
                    $errores['trabajador_' . $r['codigo']] = ($errores['trabajador_' . $r['codigo']] ?? 0) + 1;
                }
                $memoria = max($memoria, $r['memoria_mb'] ?? 0);
            }

            sort($todos);
            $fallidas = array_sum($errores);
            $procedimientos = [];
            foreach ($this->estadisticasProcedimientos() as $nombre => [$ejecuciones, $total, $maximo]) {
                $previas = $procedimientosAntes[$nombre] ?? [0, 0, 0];
                $nuevas = $ejecuciones - $previas[0];
                if ($nuevas > 0) {
                    $procedimientos[] = ['nombre' => $nombre, 'ejecuciones' => $nuevas, 'promedio_ms' => round(($total - $previas[1]) / 1000 / $nuevas, 1), 'maximo_ms' => round($maximo / 1000, 1)];
                }
            }
            usort($procedimientos, fn ($a, $b) => $b['promedio_ms'] * $b['ejecuciones'] <=> $a['promedio_ms'] * $a['ejecuciones']);

            $this->estres[] = [
                'usuarios' => $n,
                'operaciones' => count($todos),
                'exitosas' => count($todos) - $fallidas,
                'fallidas' => $fallidas,
                'errores' => $errores,
                'promedio_ms' => $todos ? round(array_sum($todos) / count($todos), 1) : 0,
                'p95_ms' => $todos ? $todos[(int) floor(0.95 * (count($todos) - 1))] : 0,
                'maximo_ms' => $todos ? max($todos) : 0,
                'por_operacion' => array_map(fn ($l) => ['n' => count($l), 'promedio_ms' => round(array_sum($l) / count($l), 1), 'maximo_ms' => max($l)], $porTipo),
                'operaciones_por_segundo' => round(count($todos) / max(1, $duracion), 1),
                'deadlocks' => $despues['deadlocks'] - $antes['deadlocks'],
                'esperas_por_bloqueo' => $despues['esperas_bloqueo'] - $antes['esperas_bloqueo'],
                'ms_esperando_bloqueos' => $despues['ms_bloqueo'] - $antes['ms_bloqueo'],
                'cpu_sql_server_pct' => $despues['cpu'],
                'memoria_php_max_mb' => $memoria,
                'procedimientos' => array_slice($procedimientos, 0, 6),
            ];

            $ultimo = end($this->estres);
            $this->line(sprintf('  %3d usuarios: %d operaciones, %d fallidas, promedio %.1f ms, p95 %s ms, máx %s ms, deadlocks %d, CPU SQL %s%%', $n, $ultimo['operaciones'], $ultimo['fallidas'], $ultimo['promedio_ms'], $ultimo['p95_ms'], $ultimo['maximo_ms'], $ultimo['deadlocks'], $ultimo['cpu_sql_server_pct']));
        }

        $this->liberarMesas();
        $this->anotar('estres', 'Prueba de estrés ejecutada en todos los niveles pedidos', 'ver tabla de estrés', count($this->estres) . ' niveles', count($this->estres) > 0);
    }

    private function estadisticasProcedimientos(): array
    {
        $estadisticas = [];

        foreach (DB::connection(self::PRUEBAS)->select('SELECT OBJECT_NAME(object_id, database_id) AS nombre, execution_count, total_elapsed_time, max_elapsed_time FROM sys.dm_exec_procedure_stats WHERE database_id = DB_ID()') as $fila) {
            $estadisticas[$fila->nombre] = [(int) $fila->execution_count, (int) $fila->total_elapsed_time, (int) $fila->max_elapsed_time];
        }

        return $estadisticas;
    }

    private function contadores(): array
    {
        $contador = fn (string $objeto, string $nombre) => (int) $this->valor(self::MASTER, "SELECT SUM(cntr_value) FROM sys.dm_os_performance_counters WHERE object_name LIKE ? AND counter_name = ? AND instance_name = '_Total'", ['%' . $objeto, $nombre]);
        $cpu = $this->valor(self::MASTER, "SELECT TOP (1) record.value('(./Record/SchedulerMonitorEvent/SystemHealth/ProcessUtilization)[1]', 'int') FROM (SELECT CONVERT(XML, record) AS record, [timestamp] FROM sys.dm_os_ring_buffers WHERE ring_buffer_type = N'RING_BUFFER_SCHEDULER_MONITOR' AND record LIKE N'%<SystemHealth>%') r ORDER BY [timestamp] DESC");

        return [
            'deadlocks' => $contador(':Locks', 'Number of Deadlocks/sec'),
            'esperas_bloqueo' => $contador(':Locks', 'Lock Waits/sec'),
            'ms_bloqueo' => $contador(':Locks', 'Lock Wait Time (ms)'),
            'cpu' => $cpu,
        ];
    }

    private function probarFallos(): void
    {
        $c = $this->contexto();
        $this->liberarMesas();

        $token = (string) Str::uuid();
        $spid = $this->interrumpir('DECLARE @id INT; EXEC dbo.sp_registrar_pedido @token_idempotencia = ?, @id_mesa = ?, @id_empleado = ?, @observaciones = NULL, @json_lineas = ?, @nuevo_id_pedido = @id OUTPUT;',
            [$token, $c['mesas'][3], $c['empleados'][0], json_encode([['num_linea' => 1, 'id_producto' => $c['producto'], 'cantidad' => 1]])]);
        $quedo = DB::connection(self::PRUEBAS)->table('PEDIDO')->where('token_idempotencia', $token)->exists();
        $mesa = DB::connection(self::PRUEBAS)->table('MESA')->where('id_mesa', $c['mesas'][3])->value('estado');
        $this->anotar('fallos', 'Laravel se cae (proceso terminado) durante el registro de un pedido', 'el pedido no queda guardado y la mesa sigue libre', "sesión {$spid}: pedido guardado=" . ($quedo ? 'sí' : 'no') . ", mesa={$mesa}", $spid > 0 && ! $quedo && $mesa === 'DISPONIBLE');

        $pedido = $this->crearPedido($c['mesas'][4], $c['empleados'][0], $c['producto']);
        $spidCobro = $this->interrumpir('DECLARE @n VARCHAR(50); EXEC dbo.sp_cobrar_cuenta @id_pedido = ?, @id_cliente = NULL, @id_cajero = ?, @descuento = 0, @json_pagos = ?, @numero_factura_out = @n OUTPUT;',
            [$pedido, $c['empleados'][1], json_encode([['id_metodo_pago' => $c['metodo'], 'monto' => 1000000]])]);
        $factura = DB::connection(self::PRUEBAS)->table('FACTURA')->where('id_pedido', $pedido)->exists();
        $estado = DB::connection(self::PRUEBAS)->table('PEDIDO')->where('id_pedido', $pedido)->value('estado');
        $this->anotar('fallos', 'Laravel se cae durante el cobro (factura y pago)', 'sin factura ni pago; el pedido sigue abierto', "sesión {$spidCobro}: factura=" . ($factura ? 'sí' : 'no') . ", estado del pedido={$estado}", $spidCobro > 0 && ! $factura && $estado === 'ABIERTO');

        $pdo = DB::connection(self::PRUEBAS)->getPdo();
        $pdo->exec("BEGIN TRANSACTION; UPDATE dbo.MESA SET zona = 'Bloqueada' WHERE id_mesa = {$c['mesas'][5]}");
        config(['database.connections.sqlsrv_espera' => config('database.connections.' . self::PRUEBAS)]);
        $otra = DB::connection('sqlsrv_espera')->getPdo();
        $otra->exec('SET LOCK_TIMEOUT 3000');
        $t = microtime(true);
        try {
            $this->drenar($otra, 'DECLARE @id INT; EXEC dbo.sp_registrar_pedido @token_idempotencia = ?, @id_mesa = ?, @id_empleado = ?, @observaciones = NULL, @json_lineas = ?, @nuevo_id_pedido = @id OUTPUT;',
                [(string) Str::uuid(), $c['mesas'][5], $c['empleados'][0], json_encode([['num_linea' => 1, 'id_producto' => $c['producto'], 'cantidad' => 1]])]);
            $espera = 'no esperó';
        } catch (PDOException $e) {
            $espera = 'error ' . ($e->errorInfo[1] ?? '?') . ' tras ' . round((microtime(true) - $t) * 1000) . ' ms';
        }
        $pdo->exec('ROLLBACK TRANSACTION');
        $huerfanas = (int) $this->escalar($otra, 'SELECT @@TRANCOUNT');
        DB::purge('sqlsrv_espera');
        $this->anotar('fallos', 'Consulta que supera el tiempo permitido (mesa bloqueada por otra transacción, LOCK_TIMEOUT 3 s)', 'error 1222 y ninguna transacción abierta', "{$espera}, transacciones abiertas después={$huerfanas}", str_contains($espera, '1222') && $huerfanas === 0);

        $victima = DB::connection('sqlsrv_espera')->getPdo();
        $spidVictima = (int) $this->escalar($victima, 'SELECT @@SPID');
        $victima->exec("BEGIN TRANSACTION; INSERT INTO dbo.CATEGORIA (nombre_categoria) VALUES ('Categoria de conexion cortada')");
        $this->lote(self::MASTER, "KILL {$spidVictima}");
        DB::purge('sqlsrv_espera');
        $quedo = DB::connection(self::PRUEBAS)->table('CATEGORIA')->where('nombre_categoria', 'Categoria de conexion cortada')->exists();
        $this->anotar('fallos', 'Conexión cortada por SQL Server (KILL) a mitad de una transacción', 'cambios revertidos', 'categoría guardada=' . ($quedo ? 'sí' : 'no'), ! $quedo);

        foreach ([
            ['Líneas de pedido con JSON roto', 'DECLARE @id INT; EXEC dbo.sp_registrar_pedido @token_idempotencia = ?, @id_mesa = ?, @id_empleado = ?, @observaciones = NULL, @json_lineas = ?, @nuevo_id_pedido = @id OUTPUT;', [(string) Str::uuid(), $c['mesas'][6], $c['empleados'][0], '{roto'], 50041],
            ['Cantidad negativa', 'DECLARE @id INT; EXEC dbo.sp_registrar_pedido @token_idempotencia = ?, @id_mesa = ?, @id_empleado = ?, @observaciones = NULL, @json_lineas = ?, @nuevo_id_pedido = @id OUTPUT;', [(string) Str::uuid(), $c['mesas'][6], $c['empleados'][0], json_encode([['num_linea' => 1, 'id_producto' => $c['producto'], 'cantidad' => -3]])], 50043],
            ['Descuento de 150 %', 'DECLARE @n VARCHAR(50); EXEC dbo.sp_cobrar_cuenta @id_pedido = 1, @id_cliente = NULL, @id_cajero = ?, @descuento = 150, @json_pagos = ?, @numero_factura_out = @n OUTPUT;', [$c['empleados'][1], '[]'], 50049],
            ['Pago insuficiente', 'DECLARE @n VARCHAR(50); EXEC dbo.sp_cobrar_cuenta @id_pedido = ?, @id_cliente = NULL, @id_cajero = ?, @descuento = 0, @json_pagos = ?, @numero_factura_out = @n OUTPUT;', [$this->crearPedido($c['mesas'][7], $c['empleados'][0], $c['producto']), $c['empleados'][1], json_encode([['id_metodo_pago' => $c['metodo'], 'monto' => 1]])], 50005],
            ['Empleado sin permiso para cobrar', 'DECLARE @n VARCHAR(50); EXEC dbo.sp_cobrar_cuenta @id_pedido = 1, @id_cliente = NULL, @id_cajero = ?, @descuento = 0, @json_pagos = ?, @numero_factura_out = @n OUTPUT;', [(int) DB::connection(self::PRUEBAS)->table('EMPLEADO as e')->join('ROL as r', 'r.id_rol', '=', 'e.id_rol')->where('r.nombre_rol', 'Cocinero')->value('e.id_empleado'), json_encode([['id_metodo_pago' => $c['metodo'], 'monto' => 10]])], 50059],
        ] as [$caso, $sql, $parametros, $esperado]) {
            $r = $this->medir(fn () => $this->lote(self::PRUEBAS, $sql, $parametros));
            $this->anotar('fallos', "Datos inválidos: {$caso}", "rechazo {$esperado}", $r['ok'] ? 'aceptado' : "rechazo {$r['codigo']}", ! $r['ok'] && (int) $r['codigo'] === $esperado);
        }

        $this->anotar('fallos', 'SQL Server se detiene o reinicia', 'Laravel muestra error y se recupera al volver', 'no automatizada: detenga el servicio "SQL Server (MSSQLSERVER01)" desde services.msc con la aplicación abierta, intente operar, vuelva a iniciarlo y repita la operación', null);
        $this->anotar('fallos', 'Falta de espacio en disco', 'error claro', 'no automatizada por riesgo: requiere una unidad pequeña dedicada', null);
        $this->liberarMesas();
    }

    private function interrumpir(string $sql, array $parametros): int
    {
        [$proceso, $tuberias] = $this->lanzar('interrumpido', base64_encode(json_encode(['sql' => 'SET NOCOUNT ON; ' . $sql, 'parametros' => $parametros])));
        $linea = fgets($tuberias[1]);
        usleep(500_000);
        $estado = proc_get_status($proceso);

        if ($estado['running'] && PHP_OS_FAMILY === 'Windows') {
            exec('taskkill /F /T /PID ' . $estado['pid'] . ' >NUL 2>&1');
        } elseif ($estado['running']) {
            proc_terminate($proceso, 9);
        }

        fclose($tuberias[1]);
        fclose($tuberias[2]);
        proc_close($proceso);
        sleep(2);
        DB::purge(self::PRUEBAS);

        return (int) (json_decode((string) $linea, true)['spid'] ?? 0);
    }

    private function medir(\Closure $accion): array
    {
        $t = microtime(true);

        try {
            $resultado = $accion();

            return ['ok' => true, 'ms' => round((microtime(true) - $t) * 1000, 1), 'resultado' => is_object($resultado) ? (array) $resultado : $resultado] + (is_array($resultado) ? $resultado : []);
        } catch (\Throwable $e) {
            $previa = $e instanceof QueryException ? $e->getPrevious() : $e;

            return ['ok' => false, 'ms' => round((microtime(true) - $t) * 1000, 1), 'codigo' => $previa instanceof PDOException ? ($previa->errorInfo[1] ?? $previa->getCode()) : 'excepcion', 'error' => Str::limit($this->mensaje($e), 300)];
        }
    }

    private function lote(string $conexion, string $sql, array $parametros = []): array
    {
        try {
            return $this->drenar(DB::connection($conexion)->getPdo(), $sql, $parametros);
        } catch (PDOException $e) {
            throw new QueryException($conexion, $sql, $parametros, $e);
        }
    }

    private function escalar(PDO $pdo, string $sql): mixed
    {
        $sentencia = $pdo->query($sql);
        $valor = $sentencia->fetchColumn();
        $sentencia->closeCursor();

        return $valor;
    }

    private function filas(string $conexion, string $sql, array $parametros = []): array
    {
        return $this->lote($conexion, $sql, $parametros);
    }

    private function fila(string $conexion, string $sql, array $parametros = []): ?array
    {
        return $this->lote($conexion, $sql, $parametros)[0] ?? null;
    }

    private function valor(string $conexion, string $sql, array $parametros = []): mixed
    {
        $fila = $this->fila($conexion, $sql, $parametros);

        return $fila === null ? null : reset($fila);
    }

    private function drenar(PDO $pdo, string $sql, array $parametros): array
    {
        $sentencia = $pdo->prepare($sql);
        $sentencia->execute($parametros);
        $ultimas = [];

        do {
            if ($sentencia->columnCount() > 0) {
                $filas = $sentencia->fetchAll(PDO::FETCH_ASSOC);

                if ($filas !== []) {
                    $ultimas = $filas;
                }
            }

            try {
                $hay = $sentencia->nextRowset();
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), 'IMSSP') && str_contains($e->getMessage(), 'no more results')) {
                    break;
                }

                throw $e;
            }
        } while ($hay);

        $sentencia->closeCursor();

        return $ultimas;
    }

    private function nombre(string $base): string
    {
        return '[' . str_replace(']', ']]', $base) . ']';
    }

    private function literal(string $texto): string
    {
        return "N'" . str_replace("'", "''", $texto) . "'";
    }

    private function mensaje(\Throwable $e): string
    {
        $texto = $e->getMessage();

        return preg_match('/\[SQL Server\](.*?)(\(Connection:|$)/s', $texto, $m) ? trim($m[1]) : $texto;
    }

    private function anotar(string $fase, string $prueba, string $esperado, string $obtenido, ?bool $ok): void
    {
        $this->resultados[] = compact('fase', 'prueba', 'esperado', 'obtenido', 'ok');
        $marca = $ok === null ? '<comment>PENDIENTE</comment>' : ($ok ? '<info>APROBADA</info>' : '<error>FALLIDA</error>');
        $this->line("  {$marca} {$prueba} → {$obtenido}");
    }

    private function escribirInforme(float $segundos): string
    {
        $carpeta = storage_path('app/pruebas');
        @mkdir($carpeta, 0775, true);
        $nombre = 'informe-sqlserver-' . date('Ymd-His');
        file_put_contents("{$carpeta}/{$nombre}.json", json_encode(['fecha' => date('c'), 'base' => $this->base, 'servidor' => config('database.connections.sqlsrv.host'), 'version' => $this->valor(self::MASTER, 'SELECT @@VERSION'), 'resultados' => $this->resultados, 'estres' => $this->estres], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $md = ['# Pruebas en SQL Server — ' . config('app.name'), '', '- Fecha: ' . date('Y-m-d H:i'), '- Base de pruebas: ' . $this->base . ' (copia de ' . $this->principal . ')', '- Duración: ' . round($segundos) . ' s', '', '| Fase | Prueba | Esperado | Obtenido | Resultado |', '|---|---|---|---|---|'];
        foreach ($this->resultados as $r) {
            $md[] = '| ' . implode(' | ', [$r['fase'], $r['prueba'], $r['esperado'], str_replace('|', '/', $r['obtenido']), $r['ok'] === null ? 'Pendiente' : ($r['ok'] ? 'Aprobada' : 'Fallida')]) . ' |';
        }

        if ($this->estres) {
            $md = array_merge($md, ['', '## Estrés', '', '| Usuarios | Operaciones | Fallidas | Prom. ms | p95 ms | Máx. ms | Op/s | Deadlocks | Esperas por bloqueo | CPU SQL % | Memoria PHP MB |', '|---|---|---|---|---|---|---|---|---|---|---|']);
            foreach ($this->estres as $e) {
                $md[] = "| {$e['usuarios']} | {$e['operaciones']} | {$e['fallidas']} | {$e['promedio_ms']} | {$e['p95_ms']} | {$e['maximo_ms']} | {$e['operaciones_por_segundo']} | {$e['deadlocks']} | {$e['esperas_por_bloqueo']} | {$e['cpu_sql_server_pct']} | {$e['memoria_php_max_mb']} |";
            }
        }

        file_put_contents("{$carpeta}/{$nombre}.md", implode("\n", $md) . "\n");

        return "{$carpeta}/{$nombre}.md";
    }
}
