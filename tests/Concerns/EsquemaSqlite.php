<?php

namespace Tests\Concerns;

use App\Models\Empleado;
use Illuminate\Support\Facades\DB;

trait EsquemaSqlite
{
    protected string $archivoBd;

    protected function prepararBaseDeDatos(): void
    {
        $this->archivoBd = tempnam(sys_get_temp_dir(), 'restaurante_') . '.sqlite';
        touch($this->archivoBd);

        $conexion = [
            'driver' => 'sqlite',
            'database' => $this->archivoBd,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ];

        config([
            'database.default' => 'sqlsrv',
            'database.connections.sqlsrv' => $conexion,
        ]);

        DB::purge('sqlsrv');

        $pdo = DB::connection('sqlsrv')->getPdo();
        $pdo->sqliteCreateFunction('GETDATE', fn () => now()->format('Y-m-d H:i:s'), 0);

        foreach ($this->sentenciasEsquema() as $sql) {
            DB::connection('sqlsrv')->statement($sql);
        }
    }

    protected function eliminarBaseDeDatos(): void
    {
        DB::purge('sqlsrv');

        if (isset($this->archivoBd) && is_file($this->archivoBd)) {
            @unlink($this->archivoBd);
        }
    }

    protected function sentenciasEsquema(): array
    {
        return [
            'CREATE TABLE ROL (id_rol INTEGER PRIMARY KEY AUTOINCREMENT, nombre_rol TEXT NOT NULL UNIQUE)',
            'CREATE TABLE PERMISO (id_permiso INTEGER PRIMARY KEY AUTOINCREMENT, clave TEXT NOT NULL UNIQUE, modulo TEXT NOT NULL, descripcion TEXT NOT NULL)',
            'CREATE TABLE ROL_PERMISO (id_rol INTEGER NOT NULL, id_permiso INTEGER NOT NULL, PRIMARY KEY (id_rol, id_permiso))',
            'CREATE VIEW vw_permisos_empleado AS SELECT e.id_empleado, p.clave FROM EMPLEADO e INNER JOIN ROL_PERMISO rp ON rp.id_rol = e.id_rol INNER JOIN PERMISO p ON p.id_permiso = rp.id_permiso WHERE e.activo = 1',
            'CREATE TABLE ESTACION (id_estacion INTEGER PRIMARY KEY AUTOINCREMENT, nombre_estacion TEXT NOT NULL)',
            'CREATE TABLE EMPLEADO (id_empleado INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT NOT NULL, apellido TEXT NOT NULL, correo TEXT NOT NULL UNIQUE, contrasena_hash TEXT NOT NULL, id_rol INTEGER NOT NULL, fecha_ingreso TEXT, activo INTEGER NOT NULL DEFAULT 1)',
            'CREATE TABLE MESERO (id_empleado INTEGER PRIMARY KEY, zona_asignada TEXT NOT NULL)',
            'CREATE TABLE COCINERO (id_empleado INTEGER PRIMARY KEY, id_estacion INTEGER NOT NULL, especialidad TEXT NOT NULL)',
            'CREATE TABLE CAJERO (id_empleado INTEGER PRIMARY KEY, codigo_caja TEXT NOT NULL)',
            'CREATE TABLE CATEGORIA (id_categoria INTEGER PRIMARY KEY AUTOINCREMENT, nombre_categoria TEXT NOT NULL, descripcion TEXT)',
            'CREATE TABLE MESA (id_mesa INTEGER PRIMARY KEY AUTOINCREMENT, numero_mesa INTEGER NOT NULL, capacidad INTEGER NOT NULL, zona TEXT NOT NULL, estado TEXT NOT NULL DEFAULT \'DISPONIBLE\')',
            'CREATE TABLE CLIENTE (id_cliente INTEGER PRIMARY KEY AUTOINCREMENT, cedula TEXT NOT NULL UNIQUE, nombre TEXT NOT NULL, apellido TEXT NOT NULL, telefono TEXT, correo TEXT)',
            'CREATE TABLE PRODUCTO (id_producto INTEGER PRIMARY KEY AUTOINCREMENT, nombre_producto TEXT NOT NULL, descripcion TEXT, precio REAL NOT NULL, id_categoria INTEGER NOT NULL, id_estacion INTEGER NOT NULL, activo INTEGER NOT NULL DEFAULT 1, foto TEXT)',
            'CREATE TABLE INSUMO (id_insumo INTEGER PRIMARY KEY AUTOINCREMENT, nombre_insumo TEXT NOT NULL, unidad_medida TEXT NOT NULL, stock_actual REAL NOT NULL, stock_minimo REAL NOT NULL, costo_unitario REAL NOT NULL)',
            'CREATE TABLE RECETA (id_producto INTEGER NOT NULL, id_insumo INTEGER NOT NULL, cantidad REAL NOT NULL, PRIMARY KEY (id_producto, id_insumo))',
            'CREATE TABLE PEDIDO (id_pedido INTEGER PRIMARY KEY AUTOINCREMENT, token_idempotencia TEXT NOT NULL UNIQUE, id_mesa INTEGER NOT NULL, id_empleado INTEGER NOT NULL, fecha_hora TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, estado TEXT NOT NULL DEFAULT \'ABIERTO\', subtotal REAL NOT NULL DEFAULT 0, observaciones TEXT, anulado_por INTEGER, motivo_anulacion TEXT)',
            'CREATE TABLE DETALLE_PEDIDO (id_pedido INTEGER NOT NULL, num_linea INTEGER NOT NULL, id_producto INTEGER NOT NULL, cantidad INTEGER NOT NULL, precio_unitario REAL NOT NULL, estado_cocina TEXT NOT NULL DEFAULT \'PENDIENTE\', notas TEXT, fecha_actualizacion TEXT DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id_pedido, num_linea))',
            'CREATE TABLE TURNO_CAJA (id_turno INTEGER PRIMARY KEY AUTOINCREMENT, id_empleado INTEGER NOT NULL, fecha_apertura TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, fecha_cierre TEXT, monto_inicial REAL NOT NULL, monto_final REAL)',
            'CREATE TABLE FACTURA (id_factura INTEGER PRIMARY KEY AUTOINCREMENT, numero_factura TEXT NOT NULL UNIQUE, id_pedido INTEGER NOT NULL UNIQUE, id_cliente INTEGER, id_empleado INTEGER NOT NULL, id_turno INTEGER NOT NULL, fecha_hora TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, subtotal REAL NOT NULL, descuento REAL NOT NULL DEFAULT 0, impuesto_ventas REAL NOT NULL, servicio REAL NOT NULL, total REAL NOT NULL, vuelto REAL NOT NULL DEFAULT 0, estado TEXT NOT NULL DEFAULT \'COBRADA\', anulada_por INTEGER, motivo_anulacion TEXT, fecha_anulacion TEXT)',
            'CREATE TABLE METODO_PAGO (id_metodo_pago INTEGER PRIMARY KEY AUTOINCREMENT, nombre_metodo TEXT NOT NULL)',
            'CREATE TABLE PAGO (id_pago INTEGER PRIMARY KEY AUTOINCREMENT, id_factura INTEGER NOT NULL, id_metodo_pago INTEGER NOT NULL, monto REAL NOT NULL, referencia TEXT, fecha_hora TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)',
            'CREATE TABLE AUDITORIA_PEDIDO (id_auditoria INTEGER PRIMARY KEY AUTOINCREMENT, id_pedido INTEGER NOT NULL, accion TEXT NOT NULL, usuario_bd TEXT NOT NULL DEFAULT \'sa\', id_empleado_app INTEGER, fecha_hora TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, estado_anterior TEXT, estado_nuevo TEXT, subtotal_anterior REAL, subtotal_nuevo REAL)',
            'CREATE TABLE ALERTA_STOCK (id_alerta INTEGER PRIMARY KEY AUTOINCREMENT, id_insumo INTEGER NOT NULL, stock_actual REAL NOT NULL, stock_minimo REAL NOT NULL, fecha_hora TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, atendida INTEGER NOT NULL DEFAULT 0)',
            'CREATE TABLE SESION_ACTIVA (id_sesion INTEGER PRIMARY KEY AUTOINCREMENT, id_empleado INTEGER NOT NULL UNIQUE, token TEXT NOT NULL, direccion_ip TEXT, navegador TEXT, fecha_inicio TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, ultima_actividad TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)',
            'CREATE TABLE RESPALDO_BD (id_respaldo INTEGER PRIMARY KEY AUTOINCREMENT, nombre_archivo TEXT NOT NULL, ruta_completa TEXT NOT NULL, fecha_hora TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, id_empleado INTEGER, verificado INTEGER NOT NULL DEFAULT 0, resultado TEXT)',
            "CREATE VIEW vw_factura_ticket AS SELECT f.numero_factura, f.fecha_hora, m.numero_mesa, emp.nombre || ' ' || emp.apellido AS cajero, f.subtotal, f.descuento, f.impuesto_ventas, f.servicio, f.total, f.vuelto, f.estado FROM FACTURA f INNER JOIN PEDIDO p ON f.id_pedido = p.id_pedido INNER JOIN MESA m ON p.id_mesa = m.id_mesa INNER JOIN EMPLEADO emp ON f.id_empleado = emp.id_empleado",
        ];
    }

    protected function sembrarDatosBase(): void
    {
        $bd = DB::connection('sqlsrv');

        $bd->table('ROL')->insert([
            ['nombre_rol' => 'Administrador'],
            ['nombre_rol' => 'Mesero'],
            ['nombre_rol' => 'Cocinero'],
            ['nombre_rol' => 'Cajero'],
        ]);
        $this->sembrarPermisos();
        $bd->table('ESTACION')->insert(['nombre_estacion' => 'Parrilla']);
        $bd->table('CATEGORIA')->insert([
            ['nombre_categoria' => 'Platos fuertes'],
            ['nombre_categoria' => 'Bebidas'],
        ]);
        $bd->table('MESA')->insert([
            ['numero_mesa' => 1, 'capacidad' => 4, 'zona' => 'Salón', 'estado' => 'DISPONIBLE'],
            ['numero_mesa' => 2, 'capacidad' => 2, 'zona' => 'Terraza', 'estado' => 'DISPONIBLE'],
        ]);
        $bd->table('PRODUCTO')->insert([
            ['nombre_producto' => 'Casado', 'descripcion' => 'Plato típico', 'precio' => 4500, 'id_categoria' => 1, 'id_estacion' => 1, 'activo' => 1, 'foto' => null],
            ['nombre_producto' => 'Refresco natural', 'descripcion' => null, 'precio' => 1500, 'id_categoria' => 2, 'id_estacion' => 1, 'activo' => 1, 'foto' => 'img/productos/predeterminadas/refresco-natural.svg'],
            ['nombre_producto' => 'Pargo frito', 'descripcion' => null, 'precio' => 7800, 'id_categoria' => 1, 'id_estacion' => 1, 'activo' => 1, 'foto' => null],
        ]);
        $bd->table('METODO_PAGO')->insert([['nombre_metodo' => 'Efectivo'], ['nombre_metodo' => 'Tarjeta']]);
    }

    protected function catalogoPermisos(): array
    {
        $sql = file_get_contents(base_path('database/sql/3_datos_iniciales.sql'));
        preg_match('/INSERT INTO dbo\.PERMISO.*?\) v\(clave/s', $sql, $bloque);
        preg_match_all("/\('([a-z_]+\.[a-z]+)', '([^']+)', '([^']+)'\)/u", $bloque[0], $filas, PREG_SET_ORDER);

        return array_map(fn ($fila) => ['clave' => $fila[1], 'modulo' => $fila[2], 'descripcion' => $fila[3]], $filas);
    }

    protected function sembrarPermisos(): void
    {
        $bd = DB::connection('sqlsrv');
        $bd->table('PERMISO')->insert($this->catalogoPermisos());

        $porDefecto = ['Mesero' => ['mesero.usar'], 'Cocinero' => ['cocina.usar'], 'Cajero' => ['caja.usar']];

        foreach ($bd->table('ROL')->get() as $rol) {
            $claves = $rol->nombre_rol === 'Administrador' ? null : ($porDefecto[$rol->nombre_rol] ?? []);
            $permisos = $bd->table('PERMISO')->when($claves !== null, fn ($q) => $q->whereIn('clave', $claves))->pluck('id_permiso');
            $bd->table('ROL_PERMISO')->insert($permisos->map(fn ($id) => ['id_rol' => $rol->id_rol, 'id_permiso' => $id])->all());
        }
    }

    protected function crearEmpleado(string $rol, string $correo, string $clave = 'secreto123', bool $activo = true): int
    {
        $bd = DB::connection('sqlsrv');
        $idRol = $bd->table('ROL')->where('nombre_rol', $rol)->value('id_rol');

        $id = $bd->table('EMPLEADO')->insertGetId([
            'nombre' => $rol,
            'apellido' => 'Prueba',
            'correo' => $correo,
            'contrasena_hash' => password_hash($clave, PASSWORD_BCRYPT, ['cost' => 4]),
            'id_rol' => $idRol,
            'fecha_ingreso' => now()->toDateTimeString(),
            'activo' => $activo ? 1 : 0,
        ], 'id_empleado');

        if ($rol === 'Mesero') {
            $bd->table('MESERO')->insert(['id_empleado' => $id, 'zona_asignada' => 'Salón']);
        } elseif ($rol === 'Cajero') {
            $bd->table('CAJERO')->insert(['id_empleado' => $id, 'codigo_caja' => 'CAJA-' . $id]);
        } elseif ($rol === 'Cocinero') {
            $bd->table('COCINERO')->insert(['id_empleado' => $id, 'id_estacion' => 1, 'especialidad' => 'General']);
        }

        return $id;
    }

    protected function iniciarSesionComo(int $idEmpleado): static
    {
        return $this->actingAs(Empleado::findOrFail($idEmpleado));
    }
}
