<?php

namespace App\Http\Controllers;

use App\Models\RespaldoBd;
use App\Support\SesionUnica;
use App\Support\Sp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RespaldoController extends Controller
{
    public function index()
    {
        try {
            $items = RespaldoBd::with('empleado')->orderByDesc('fecha_hora')->paginate(15);
        } catch (\Throwable $e) {
            Log::warning('No se pudo leer el historial de respaldos', ['error' => $e->getMessage()]);

            $items = null;
        }

        return view('admin.respaldos.index', [
            'items' => $items,
            'ruta' => $this->carpetaRespaldos(),
            'rutaEnv' => config('restaurante.respaldos.ruta'),
            'rutaSqlServer' => $this->carpetaSqlServer(),
        ]);
    }

    public function store(Request $request)
    {
        try {
            $archivo = $this->generarRespaldo((int) $request->user()->id_empleado);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Error al generar respaldo', ['error' => $e->getMessage()]);

            return back()->with('error', 'No se pudo generar el respaldo: ' . $this->mensajeSql($e));
        }

        return back()->with('success', 'Respaldo generado y verificado correctamente: ' . $archivo);
    }

    public function verificar($id)
    {
        $respaldo = RespaldoBd::findOrFail($id);

        try {
            Sp::lote('EXEC sp_verificar_respaldo @id_respaldo = ?', [$respaldo->id_respaldo]);
        } catch (\Throwable $e) {
            Log::error('Falló la verificación del respaldo', ['respaldo' => $respaldo->id_respaldo, 'error' => $e->getMessage()]);

            return back()->with('error', 'El respaldo no pasó la verificación: ' . $this->mensajeSql($e));
        }

        return back()->with('success', 'El respaldo ' . $respaldo->nombre_archivo . ' está íntegro.');
    }

    public function confirmarRestauracion(Request $request)
    {
        $respaldos = RespaldoBd::with('empleado')
            ->where('verificado', 1)
            ->orderByDesc('fecha_hora')
            ->get();

        if ($respaldos->isEmpty()) {
            return redirect()->route('admin.respaldos.index')
                ->with('error', 'No hay respaldos verificados para restaurar. Genera uno primero.');
        }

        $elegido = (int) $request->query('respaldo', $respaldos->first()->id_respaldo);

        return view('admin.respaldos.restaurar', compact('respaldos', 'elegido'));
    }

    public function restaurar(Request $request)
    {
        $datos = $request->validate([
            'id_respaldo' => ['required', 'integer'],
            'contrasena' => ['required', 'string', 'max:100'],
            'confirmo' => ['accepted'],
        ], [
            'confirmo.accepted' => 'Marca la casilla para confirmar que entiendes que los datos actuales se reemplazan.',
        ]);

        $administrador = $request->user();

        if (! Hash::check($datos['contrasena'], $administrador->getAuthPassword())) {
            throw ValidationException::withMessages(['contrasena' => 'La contraseña no es correcta.']);
        }

        $respaldo = RespaldoBd::where('id_respaldo', $datos['id_respaldo'])->where('verificado', 1)->first();

        if (! $respaldo) {
            return back()->with('error', 'Ese respaldo no existe o no está verificado.');
        }

        if (! Sp::disponibles()) {
            return back()->with('error', 'La restauración solo está disponible con SQL Server.');
        }

        $candado = Cache::store('file')->lock('restaurando-base-de-datos', 900);

        if (! $candado->get()) {
            return back()->with('error', 'Ya hay una restauración en curso. Espera a que termine.');
        }

        @set_time_limit(0);

        try {
            $previo = $this->generarRespaldo((int) $administrador->id_empleado, 'Respaldo automático antes de restaurar ' . $respaldo->nombre_archivo . '.');
            $historial = DB::connection('sqlsrv')->table('RESPALDO_BD')->get()->map(fn ($fila) => (array) $fila)->all();

            $this->ejecutarRestauracion($respaldo->ruta_completa);

            $this->recuperarHistorial($historial, $respaldo->nombre_archivo, $administrador->nombre . ' ' . $administrador->apellido);
            $faltantes = $this->objetosFaltantes();
            $this->reiniciarSesiones($request);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Falló la restauración de la base de datos', ['respaldo' => $respaldo->nombre_archivo, 'error' => $e->getMessage()]);
            DB::purge('sqlsrv');

            return back()->with('error', 'No se pudo restaurar la base de datos: ' . $this->mensajeSql($e));
        } finally {
            $candado->release();
        }

        if (! Auth::check()) {
            return redirect()->route('login')->withErrors(['correo' => 'La base de datos se restauró, pero tu usuario no existe en ese respaldo. Entra con una cuenta que sí exista.']);
        }

        $mensaje = 'Base de datos restaurada con el respaldo ' . $respaldo->nombre_archivo
            . '. Antes se guardó el estado anterior en ' . basename(str_replace('\\', '/', $previo))
            . '. Todos los demás usuarios deben volver a iniciar sesión.';

        $redireccion = redirect()->route('admin.respaldos.index')->with('success', $mensaje);

        if ($faltantes !== []) {
            $redireccion->with('error', 'Ese respaldo es de una versión anterior de la base y le faltan: ' . implode(', ', $faltantes)
                . '. Ejecuta otra vez 1_RestauranteDB_estructura.sql y 2_RestauranteDB_programacion.sql para recrearlos sin perder los datos restaurados.');
        }

        return $redireccion;
    }

    private function generarRespaldo(int $idEmpleado, ?string $nota = null): string
    {
        $ruta = $this->carpetaRespaldos();

        if (blank($ruta)) {
            throw new \RuntimeException('No hay carpeta de respaldos: escribe DB_BACKUP_PATH en el archivo .env o configura la carpeta de respaldos de SQL Server.');
        }

        if ($this->rutaPublica($ruta)) {
            throw new \RuntimeException('La carpeta de respaldos no puede estar dentro de la carpeta pública del proyecto.');
        }

        $resultado = Sp::lote(
            'SET NOCOUNT ON;
             DECLARE @id INT, @archivo NVARCHAR(460);
             EXEC sp_respaldar_bd @ruta = ?, @id_empleado = ?, @id_respaldo = @id OUTPUT, @archivo = @archivo OUTPUT;
             SELECT @id AS id_respaldo, @archivo AS archivo;',
            [$ruta, $idEmpleado]
        );

        $archivo = $resultado->archivo ?? null;

        if (! $archivo) {
            throw new \RuntimeException('SQL Server no confirmó el archivo del respaldo.');
        }

        if ($nota !== null && isset($resultado->id_respaldo)) {
            DB::connection('sqlsrv')->table('RESPALDO_BD')
                ->where('id_respaldo', $resultado->id_respaldo)
                ->update(['resultado' => mb_substr($nota, 0, 500)]);
        }

        return $archivo;
    }

    private function ejecutarRestauracion(string $archivo): void
    {
        $base = (string) config('database.connections.sqlsrv.database');

        config(['database.connections.sqlsrv_master' => array_merge(
            config('database.connections.sqlsrv'),
            ['database' => 'master']
        )]);

        DB::disconnect('sqlsrv');
        DB::purge('sqlsrv_master');

        try {
            Sp::lote(
                'EXEC master.dbo.sp_restaurar_restaurante @base = ?, @archivo = ?',
                [$base, $archivo],
                'sqlsrv_master'
            );
        } finally {
            DB::disconnect('sqlsrv_master');
            DB::purge('sqlsrv');
        }
    }

    private function recuperarHistorial(array $historial, string $archivoRestaurado, string $responsable): void
    {
        $tabla = DB::connection('sqlsrv')->table('RESPALDO_BD');
        $existentes = $tabla->pluck('nombre_archivo')->map(fn ($nombre) => (string) $nombre)->all();

        foreach ($historial as $fila) {
            if (in_array((string) $fila['nombre_archivo'], $existentes, true)) {
                continue;
            }

            unset($fila['id_respaldo']);
            DB::connection('sqlsrv')->table('RESPALDO_BD')->insert($fila);
        }

        DB::connection('sqlsrv')->table('RESPALDO_BD')
            ->where('nombre_archivo', $archivoRestaurado)
            ->update(['resultado' => mb_substr('Restaurado el ' . now()->format('d/m/Y H:i') . ' por ' . $responsable . '.', 0, 500)]);
    }

    private function objetosFaltantes(): array
    {
        $objetos = [
            'SESION_ACTIVA' => 'U',
            'sp_sesion_abrir' => 'P',
            'sp_sesion_validar' => 'P',
            'sp_sesion_cerrar' => 'P',
            'sp_respaldar_bd' => 'P',
        ];

        $faltantes = [];

        foreach ($objetos as $nombre => $tipo) {
            $fila = DB::connection('sqlsrv')->selectOne('SELECT OBJECT_ID(?, ?) AS id', ['dbo.' . $nombre, $tipo]);

            if (! $fila || $fila->id === null) {
                $faltantes[] = $nombre;
            }
        }

        return $faltantes;
    }

    private function reiniciarSesiones(Request $request): void
    {
        $administrador = $request->user();
        $vigente = DB::connection('sqlsrv')->table('EMPLEADO')
            ->where('id_empleado', $administrador->id_empleado)
            ->where('activo', 1)
            ->exists();

        try {
            DB::connection('sqlsrv')->table('SESION_ACTIVA')->delete();
        } catch (\Throwable $e) {
            Log::warning('No se pudo limpiar SESION_ACTIVA después de restaurar', ['error' => $e->getMessage()]);
        }

        $request->session()->forget(SesionUnica::CLAVE);

        if (! $vigente) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return;
        }

        try {
            SesionUnica::abrir($administrador->fresh(), $request);
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar la sesión después de restaurar', ['error' => $e->getMessage()]);
        }
    }

    private function carpetaRespaldos(): ?string
    {
        $ruta = config('restaurante.respaldos.ruta');

        return filled($ruta) ? (string) $ruta : $this->carpetaSqlServer();
    }

    private function carpetaSqlServer(): ?string
    {
        if (! Sp::disponibles()) {
            return null;
        }

        try {
            $fila = DB::connection('sqlsrv')->selectOne("SELECT CAST(SERVERPROPERTY('InstanceDefaultBackupPath') AS NVARCHAR(260)) AS ruta");
        } catch (\Throwable $e) {
            return null;
        }

        return filled($fila->ruta ?? null) ? (string) $fila->ruta : null;
    }

    private function rutaPublica(string $ruta): bool
    {
        $normalizar = fn (string $valor) => rtrim(mb_strtolower(str_replace('\\', '/', $valor)), '/');

        return str_starts_with($normalizar($ruta) . '/', $normalizar(public_path()) . '/');
    }
}
