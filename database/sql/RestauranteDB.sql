/* ============================================================================
   RestauranteDB  -  Sistema de pedidos, cocina y facturación para restaurante
   IF0010 Bases de Datos II  -  Sede del Caribe  -  II ciclo 2026

   Este es el ÚNICO script de la base de datos relacional. Se puede ejecutar
   las veces que haga falta: crea lo que no existe y reemplaza vistas, triggers
   y procedimientos con CREATE OR ALTER, sin borrar datos.

   Orden de ejecución en SSMS (o con sqlcmd -I):
       1) RestauranteDB.sql       <- este archivo
       2) datos_iniciales.sql     <- catálogos y un usuario de prueba por rol

   Contenido
       1. Tablas (21)
       2. Restricciones de integridad adicionales
       3. Índices
       4. Vistas
       5. Triggers (8, todos con TRY/CATCH)
       6. Procedimientos básicos: insertar / actualizar / eliminar / consultar
          para 11 de las 21 tablas (44 procedimientos)
       7. Procedimientos de negocio (transaccionales), reporte y respaldos
       8. Roles, usuarios y permisos

   Códigos de error propios (THROW)
       50001-50099  procedimientos de negocio y triggers
       50100-50109  procedimientos básicos (50100 dato obligatorio,
                    50101 no existe, 50102 tiene registros relacionados,
                    50103 valor repetido)
   ========================================================================== */

USE master;
GO

IF DB_ID(N'RestauranteDB') IS NULL
    CREATE DATABASE RestauranteDB;
GO

-- Los reportes se leen con aislamiento SNAPSHOT: no bloquean a cocina ni a caja.
ALTER DATABASE RestauranteDB SET ALLOW_SNAPSHOT_ISOLATION ON;
GO

USE RestauranteDB;
GO

SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
SET ANSI_PADDING ON;
SET ANSI_WARNINGS ON;
SET CONCAT_NULL_YIELDS_NULL ON;
SET ARITHABORT ON;
SET NUMERIC_ROUNDABORT OFF;
GO

/* ============================================================================
   1. TABLAS
   ========================================================================== */
PRINT N'1/8 Tablas';
GO

-- Catálogos pequeños -------------------------------------------------------

IF OBJECT_ID(N'dbo.ROL', N'U') IS NULL
CREATE TABLE dbo.ROL (
    id_rol      INT IDENTITY(1,1) PRIMARY KEY,
    nombre_rol  VARCHAR(50) NOT NULL UNIQUE
);

IF OBJECT_ID(N'dbo.ESTACION', N'U') IS NULL
CREATE TABLE dbo.ESTACION (
    id_estacion      INT IDENTITY(1,1) PRIMARY KEY,
    nombre_estacion  VARCHAR(50) NOT NULL UNIQUE
);

IF OBJECT_ID(N'dbo.CATEGORIA', N'U') IS NULL
CREATE TABLE dbo.CATEGORIA (
    id_categoria      INT IDENTITY(1,1) PRIMARY KEY,
    nombre_categoria  VARCHAR(50) NOT NULL UNIQUE,
    descripcion       VARCHAR(200) NULL
);

IF OBJECT_ID(N'dbo.METODO_PAGO', N'U') IS NULL
CREATE TABLE dbo.METODO_PAGO (
    id_metodo_pago  INT IDENTITY(1,1) PRIMARY KEY,
    nombre_metodo   VARCHAR(50) NOT NULL UNIQUE
);

-- Personal: EMPLEADO es la superclase; MESERO, COCINERO y CAJERO las subclases

IF OBJECT_ID(N'dbo.EMPLEADO', N'U') IS NULL
CREATE TABLE dbo.EMPLEADO (
    id_empleado      INT IDENTITY(1,1) PRIMARY KEY,
    nombre           VARCHAR(50) NOT NULL,
    apellido         VARCHAR(50) NOT NULL,
    correo           VARCHAR(100) NOT NULL UNIQUE,
    contrasena_hash  VARCHAR(255) NOT NULL,          -- bcrypt, nunca texto plano
    id_rol           INT NOT NULL,
    fecha_ingreso    DATETIME NOT NULL DEFAULT GETDATE(),
    activo           BIT NOT NULL DEFAULT 1,
    CONSTRAINT FK_EMPLEADO_ROL FOREIGN KEY (id_rol) REFERENCES dbo.ROL(id_rol)
);

IF OBJECT_ID(N'dbo.MESERO', N'U') IS NULL
CREATE TABLE dbo.MESERO (
    id_empleado    INT PRIMARY KEY,
    zona_asignada  VARCHAR(50) NOT NULL,
    CONSTRAINT FK_MESERO_EMPLEADO FOREIGN KEY (id_empleado) REFERENCES dbo.EMPLEADO(id_empleado) ON DELETE CASCADE
);

IF OBJECT_ID(N'dbo.COCINERO', N'U') IS NULL
CREATE TABLE dbo.COCINERO (
    id_empleado   INT PRIMARY KEY,
    id_estacion   INT NOT NULL,
    especialidad  VARCHAR(50) NOT NULL,
    CONSTRAINT FK_COCINERO_EMPLEADO FOREIGN KEY (id_empleado) REFERENCES dbo.EMPLEADO(id_empleado) ON DELETE CASCADE,
    CONSTRAINT FK_COCINERO_ESTACION FOREIGN KEY (id_estacion) REFERENCES dbo.ESTACION(id_estacion)
);

IF OBJECT_ID(N'dbo.CAJERO', N'U') IS NULL
CREATE TABLE dbo.CAJERO (
    id_empleado  INT PRIMARY KEY,
    codigo_caja  VARCHAR(20) NOT NULL UNIQUE,
    CONSTRAINT FK_CAJERO_EMPLEADO FOREIGN KEY (id_empleado) REFERENCES dbo.EMPLEADO(id_empleado) ON DELETE CASCADE
);

-- Salón, clientes, menú e inventario ----------------------------------------

IF OBJECT_ID(N'dbo.MESA', N'U') IS NULL
CREATE TABLE dbo.MESA (
    id_mesa      INT IDENTITY(1,1) PRIMARY KEY,
    numero_mesa  INT NOT NULL UNIQUE,
    capacidad    INT NOT NULL CHECK (capacidad > 0),
    zona         VARCHAR(50) NOT NULL,
    estado       VARCHAR(20) NOT NULL DEFAULT 'DISPONIBLE'
                 CHECK (estado IN ('DISPONIBLE', 'OCUPADA', 'RESERVADA', 'MANTENIMIENTO'))
);

IF OBJECT_ID(N'dbo.CLIENTE', N'U') IS NULL
CREATE TABLE dbo.CLIENTE (
    id_cliente  INT IDENTITY(1,1) PRIMARY KEY,
    cedula      VARBINARY(256) NOT NULL,              -- cifrada con AES-256 por la aplicación
    nombre      VARCHAR(50) NOT NULL,
    apellido    VARCHAR(50) NOT NULL,
    telefono    VARCHAR(20) NULL,
    correo      VARCHAR(100) NULL
);

IF OBJECT_ID(N'dbo.PRODUCTO', N'U') IS NULL
CREATE TABLE dbo.PRODUCTO (
    id_producto      INT IDENTITY(1,1) PRIMARY KEY,
    nombre_producto  VARCHAR(100) NOT NULL UNIQUE,
    descripcion      VARCHAR(255) NULL,
    precio           DECIMAL(10,2) NOT NULL CHECK (precio >= 0),
    id_categoria     INT NOT NULL,
    id_estacion      INT NOT NULL,
    activo           BIT NOT NULL DEFAULT 1,
    CONSTRAINT FK_PRODUCTO_CATEGORIA FOREIGN KEY (id_categoria) REFERENCES dbo.CATEGORIA(id_categoria),
    CONSTRAINT FK_PRODUCTO_ESTACION FOREIGN KEY (id_estacion) REFERENCES dbo.ESTACION(id_estacion)
);

IF OBJECT_ID(N'dbo.INSUMO', N'U') IS NULL
CREATE TABLE dbo.INSUMO (
    id_insumo       INT IDENTITY(1,1) PRIMARY KEY,
    nombre_insumo   VARCHAR(100) NOT NULL UNIQUE,
    unidad_medida   VARCHAR(20) NOT NULL,
    stock_actual    DECIMAL(10,2) NOT NULL CHECK (stock_actual >= 0),
    stock_minimo    DECIMAL(10,2) NOT NULL CHECK (stock_minimo >= 0),
    costo_unitario  DECIMAL(10,2) NOT NULL CHECK (costo_unitario >= 0)
);

IF OBJECT_ID(N'dbo.RECETA', N'U') IS NULL
CREATE TABLE dbo.RECETA (
    id_producto  INT NOT NULL,
    id_insumo    INT NOT NULL,
    cantidad     DECIMAL(10,2) NOT NULL CHECK (cantidad > 0),
    PRIMARY KEY (id_producto, id_insumo),
    CONSTRAINT FK_RECETA_PRODUCTO FOREIGN KEY (id_producto) REFERENCES dbo.PRODUCTO(id_producto) ON DELETE CASCADE,
    CONSTRAINT FK_RECETA_INSUMO FOREIGN KEY (id_insumo) REFERENCES dbo.INSUMO(id_insumo)
);

-- Operación: pedidos y cocina -----------------------------------------------

IF OBJECT_ID(N'dbo.PEDIDO', N'U') IS NULL
CREATE TABLE dbo.PEDIDO (
    id_pedido           INT IDENTITY(1,1) PRIMARY KEY,
    token_idempotencia  VARCHAR(100) NOT NULL UNIQUE,  -- lo genera la pantalla del mesero; evita pedidos duplicados
    id_mesa             INT NOT NULL,
    id_empleado         INT NOT NULL,
    fecha_hora          DATETIME NOT NULL DEFAULT GETDATE(),
    estado              VARCHAR(20) NOT NULL DEFAULT 'ABIERTO'
                        CHECK (estado IN ('ABIERTO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO', 'FACTURADO', 'ANULADO')),
    subtotal            DECIMAL(10,2) NOT NULL DEFAULT 0.00 CHECK (subtotal >= 0),
    observaciones       VARCHAR(255) NULL,
    anulado_por         INT NULL,
    motivo_anulacion    VARCHAR(255) NULL,
    CONSTRAINT FK_PEDIDO_MESA FOREIGN KEY (id_mesa) REFERENCES dbo.MESA(id_mesa),
    CONSTRAINT FK_PEDIDO_MESERO FOREIGN KEY (id_empleado) REFERENCES dbo.EMPLEADO(id_empleado),
    CONSTRAINT FK_PEDIDO_ANULADO_POR FOREIGN KEY (anulado_por) REFERENCES dbo.EMPLEADO(id_empleado)
);

IF OBJECT_ID(N'dbo.DETALLE_PEDIDO', N'U') IS NULL
CREATE TABLE dbo.DETALLE_PEDIDO (
    id_pedido            INT NOT NULL,
    num_linea            INT NOT NULL,
    id_producto          INT NOT NULL,
    cantidad             INT NOT NULL CHECK (cantidad > 0),
    precio_unitario      DECIMAL(10,2) NOT NULL CHECK (precio_unitario >= 0),  -- precio al momento de pedir
    estado_cocina        VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE'
                         CHECK (estado_cocina IN ('PENDIENTE', 'PREPARANDO', 'LISTO', 'ENTREGADO', 'CANCELADO')),
    notas                VARCHAR(200) NULL,
    fecha_actualizacion  DATETIME NOT NULL DEFAULT GETDATE(),
    PRIMARY KEY (id_pedido, num_linea),
    CONSTRAINT FK_DETALLE_PEDIDO FOREIGN KEY (id_pedido) REFERENCES dbo.PEDIDO(id_pedido) ON DELETE CASCADE,
    CONSTRAINT FK_DETALLE_PRODUCTO FOREIGN KEY (id_producto) REFERENCES dbo.PRODUCTO(id_producto)
);

-- Caja: turnos, facturas y pagos --------------------------------------------

IF OBJECT_ID(N'dbo.TURNO_CAJA', N'U') IS NULL
CREATE TABLE dbo.TURNO_CAJA (
    id_turno        INT IDENTITY(1,1) PRIMARY KEY,
    id_empleado     INT NOT NULL,
    fecha_apertura  DATETIME NOT NULL DEFAULT GETDATE(),
    fecha_cierre    DATETIME NULL,
    monto_inicial   DECIMAL(10,2) NOT NULL CHECK (monto_inicial >= 0),
    monto_final     DECIMAL(10,2) NULL CHECK (monto_final >= 0),
    CONSTRAINT FK_TURNO_CAJERO FOREIGN KEY (id_empleado) REFERENCES dbo.EMPLEADO(id_empleado)
);

IF OBJECT_ID(N'dbo.FACTURA', N'U') IS NULL
CREATE TABLE dbo.FACTURA (
    id_factura        INT IDENTITY(1,1) PRIMARY KEY,
    numero_factura    VARCHAR(50) NOT NULL UNIQUE,
    id_pedido         INT NOT NULL UNIQUE,             -- un pedido se factura una sola vez
    id_cliente        INT NULL,
    id_empleado       INT NOT NULL,
    id_turno          INT NOT NULL,
    fecha_hora        DATETIME NOT NULL DEFAULT GETDATE(),
    subtotal          DECIMAL(10,2) NOT NULL CHECK (subtotal >= 0),
    descuento         DECIMAL(10,2) NOT NULL DEFAULT 0 CHECK (descuento >= 0),
    impuesto_ventas   DECIMAL(10,2) NOT NULL CHECK (impuesto_ventas >= 0),
    servicio          DECIMAL(10,2) NOT NULL CHECK (servicio >= 0),
    total             DECIMAL(10,2) NOT NULL CHECK (total >= 0),
    vuelto            DECIMAL(10,2) NOT NULL DEFAULT 0 CHECK (vuelto >= 0),
    estado            VARCHAR(20) NOT NULL DEFAULT 'COBRADA' CHECK (estado IN ('COBRADA', 'ANULADA')),
    anulada_por       INT NULL,
    motivo_anulacion  VARCHAR(255) NULL,
    fecha_anulacion   DATETIME NULL,
    CONSTRAINT FK_FACTURA_PEDIDO FOREIGN KEY (id_pedido) REFERENCES dbo.PEDIDO(id_pedido),
    CONSTRAINT FK_FACTURA_CLIENTE FOREIGN KEY (id_cliente) REFERENCES dbo.CLIENTE(id_cliente),
    CONSTRAINT FK_FACTURA_CAJERO FOREIGN KEY (id_empleado) REFERENCES dbo.EMPLEADO(id_empleado),
    CONSTRAINT FK_FACTURA_TURNO FOREIGN KEY (id_turno) REFERENCES dbo.TURNO_CAJA(id_turno),
    CONSTRAINT FK_FACTURA_ANULADA_POR FOREIGN KEY (anulada_por) REFERENCES dbo.EMPLEADO(id_empleado)
);

IF OBJECT_ID(N'dbo.PAGO', N'U') IS NULL
CREATE TABLE dbo.PAGO (
    id_pago         INT IDENTITY(1,1) PRIMARY KEY,
    id_factura      INT NOT NULL,
    id_metodo_pago  INT NOT NULL,
    monto           DECIMAL(10,2) NOT NULL CHECK (monto > 0),
    referencia      VARCHAR(100) NULL,
    fecha_hora      DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_PAGO_FACTURA FOREIGN KEY (id_factura) REFERENCES dbo.FACTURA(id_factura),
    CONSTRAINT FK_PAGO_METODO FOREIGN KEY (id_metodo_pago) REFERENCES dbo.METODO_PAGO(id_metodo_pago)
);

-- Bitácoras y utilidades ----------------------------------------------------

IF OBJECT_ID(N'dbo.AUDITORIA_PEDIDO', N'U') IS NULL
CREATE TABLE dbo.AUDITORIA_PEDIDO (
    id_auditoria       INT IDENTITY(1,1) PRIMARY KEY,
    id_pedido          INT NOT NULL,
    accion             VARCHAR(20) NOT NULL,
    usuario_bd         VARCHAR(100) NOT NULL DEFAULT SUSER_SNAME(),
    id_empleado_app    INT NULL,
    fecha_hora         DATETIME NOT NULL DEFAULT GETDATE(),
    estado_anterior    VARCHAR(20) NULL,
    estado_nuevo       VARCHAR(20) NULL,
    subtotal_anterior  DECIMAL(10,2) NULL,
    subtotal_nuevo     DECIMAL(10,2) NULL
);

IF OBJECT_ID(N'dbo.ALERTA_STOCK', N'U') IS NULL
CREATE TABLE dbo.ALERTA_STOCK (
    id_alerta     INT IDENTITY(1,1) PRIMARY KEY,
    id_insumo     INT NOT NULL,
    stock_actual  DECIMAL(10,2) NOT NULL,
    stock_minimo  DECIMAL(10,2) NOT NULL,
    fecha_hora    DATETIME NOT NULL DEFAULT GETDATE(),
    atendida      BIT NOT NULL DEFAULT 0,
    CONSTRAINT FK_ALERTA_INSUMO FOREIGN KEY (id_insumo) REFERENCES dbo.INSUMO(id_insumo)
);

IF OBJECT_ID(N'dbo.RESPALDO_BD', N'U') IS NULL
CREATE TABLE dbo.RESPALDO_BD (
    id_respaldo     INT IDENTITY(1,1) PRIMARY KEY,
    nombre_archivo  NVARCHAR(200) NOT NULL,
    ruta_completa   NVARCHAR(460) NOT NULL,
    fecha_hora      DATETIME NOT NULL CONSTRAINT DF_RESPALDO_BD_fecha DEFAULT GETDATE(),
    id_empleado     INT NULL,
    verificado      BIT NOT NULL CONSTRAINT DF_RESPALDO_BD_verificado DEFAULT 0,
    resultado       VARCHAR(500) NULL,
    CONSTRAINT FK_RESPALDO_BD_EMPLEADO FOREIGN KEY (id_empleado) REFERENCES dbo.EMPLEADO(id_empleado)
);
GO

-- Columnas que no existían en las primeras versiones de la base (por si se
-- ejecuta sobre una base ya creada; en una base nueva no hace nada).
IF COL_LENGTH(N'dbo.FACTURA', N'anulada_por') IS NULL
    ALTER TABLE dbo.FACTURA ADD anulada_por INT NULL;
IF COL_LENGTH(N'dbo.FACTURA', N'motivo_anulacion') IS NULL
    ALTER TABLE dbo.FACTURA ADD motivo_anulacion VARCHAR(255) NULL;
IF COL_LENGTH(N'dbo.FACTURA', N'fecha_anulacion') IS NULL
    ALTER TABLE dbo.FACTURA ADD fecha_anulacion DATETIME NULL;
GO

IF OBJECT_ID(N'dbo.FK_FACTURA_ANULADA_POR', N'F') IS NULL
    ALTER TABLE dbo.FACTURA ADD CONSTRAINT FK_FACTURA_ANULADA_POR FOREIGN KEY (anulada_por) REFERENCES dbo.EMPLEADO(id_empleado);
GO

/* ============================================================================
   2. RESTRICCIONES DE INTEGRIDAD ADICIONALES
   Si la base ya tiene filas que las incumplen, el script avisa y no las crea
   (así nunca falla a mitad de camino).
   ========================================================================== */
PRINT N'2/8 Restricciones de integridad';
GO

IF OBJECT_ID(N'dbo.CK_PEDIDO_Anulacion', N'C') IS NULL
BEGIN
    IF EXISTS (SELECT 1 FROM dbo.PEDIDO WHERE estado = 'ANULADO' AND (anulado_por IS NULL OR motivo_anulacion IS NULL))
        PRINT N'AVISO: hay pedidos anulados sin responsable o motivo; no se creó CK_PEDIDO_Anulacion.';
    ELSE
        ALTER TABLE dbo.PEDIDO ADD CONSTRAINT CK_PEDIDO_Anulacion
            CHECK (estado <> 'ANULADO' OR (anulado_por IS NOT NULL AND motivo_anulacion IS NOT NULL));
END;

IF OBJECT_ID(N'dbo.CK_FACTURA_Anulacion', N'C') IS NULL
BEGIN
    IF EXISTS (SELECT 1 FROM dbo.FACTURA WHERE estado = 'ANULADA' AND (anulada_por IS NULL OR motivo_anulacion IS NULL OR fecha_anulacion IS NULL))
        PRINT N'AVISO: hay facturas anuladas sin responsable, motivo o fecha; no se creó CK_FACTURA_Anulacion.';
    ELSE
        ALTER TABLE dbo.FACTURA ADD CONSTRAINT CK_FACTURA_Anulacion
            CHECK (estado <> 'ANULADA' OR (anulada_por IS NOT NULL AND motivo_anulacion IS NOT NULL AND fecha_anulacion IS NOT NULL));
END;

IF OBJECT_ID(N'dbo.CK_FACTURA_Total', N'C') IS NULL
BEGIN
    IF EXISTS (SELECT 1 FROM dbo.FACTURA WHERE descuento > subtotal OR total <> subtotal - descuento + impuesto_ventas + servicio)
        PRINT N'AVISO: hay facturas cuyo total no cuadra; no se creó CK_FACTURA_Total.';
    ELSE
        ALTER TABLE dbo.FACTURA ADD CONSTRAINT CK_FACTURA_Total
            CHECK (descuento <= subtotal AND total = subtotal - descuento + impuesto_ventas + servicio);
END;

IF OBJECT_ID(N'dbo.CK_TURNO_CAJA_Cierre', N'C') IS NULL
BEGIN
    IF EXISTS (SELECT 1 FROM dbo.TURNO_CAJA WHERE fecha_cierre IS NOT NULL AND (fecha_cierre < fecha_apertura OR monto_final IS NULL))
        PRINT N'AVISO: hay turnos cerrados antes de abrirse o sin monto final; no se creó CK_TURNO_CAJA_Cierre.';
    ELSE
        ALTER TABLE dbo.TURNO_CAJA ADD CONSTRAINT CK_TURNO_CAJA_Cierre
            CHECK (fecha_cierre IS NULL OR (fecha_cierre >= fecha_apertura AND monto_final IS NOT NULL));
END;
GO

/* ============================================================================
   3. ÍNDICES
   Cada uno responde a una consulta concreta de la aplicación (ver documento,
   sección "Consultas más comunes e índices").
   ========================================================================== */
PRINT N'3/8 Índices';
GO

-- Cola de cocina: líneas PENDIENTE/PREPARANDO agrupadas por pedido (cada 5 s)
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_DETALLE_PEDIDO_Cocina' AND object_id = OBJECT_ID(N'dbo.DETALLE_PEDIDO'))
    CREATE NONCLUSTERED INDEX IX_DETALLE_PEDIDO_Cocina ON dbo.DETALLE_PEDIDO (estado_cocina, id_pedido);

-- Pantalla del mesero: ¿qué pedido abierto tiene cada mesa?
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_PEDIDO_MesaEstado' AND object_id = OBJECT_ID(N'dbo.PEDIDO'))
    CREATE NONCLUSTERED INDEX IX_PEDIDO_MesaEstado ON dbo.PEDIDO (id_mesa, estado);

-- Caja y dashboard: pedidos por estado y fecha, sin ir a la tabla base
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_PEDIDO_Estado_Fecha' AND object_id = OBJECT_ID(N'dbo.PEDIDO'))
    CREATE NONCLUSTERED INDEX IX_PEDIDO_Estado_Fecha ON dbo.PEDIDO (estado, fecha_hora) INCLUDE (id_mesa, subtotal);

-- Reporte de ventas por rango de fechas
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_FACTURA_FechaTotal' AND object_id = OBJECT_ID(N'dbo.FACTURA'))
    CREATE NONCLUSTERED INDEX IX_FACTURA_FechaTotal ON dbo.FACTURA (fecha_hora) INCLUDE (estado, total);

-- Resumen del cierre de turno
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_FACTURA_Turno' AND object_id = OBJECT_ID(N'dbo.FACTURA'))
    CREATE NONCLUSTERED INDEX IX_FACTURA_Turno ON dbo.FACTURA (id_turno) INCLUDE (estado, total, vuelto);

-- Menú del mesero: productos activos por categoría
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_PRODUCTO_CategoriaActivo' AND object_id = OBJECT_ID(N'dbo.PRODUCTO'))
    CREATE NONCLUSTERED INDEX IX_PRODUCTO_CategoriaActivo ON dbo.PRODUCTO (id_categoria, activo) INCLUDE (nombre_producto, precio);

-- Alertas pendientes del dashboard
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_ALERTA_STOCK_Pendiente' AND object_id = OBJECT_ID(N'dbo.ALERTA_STOCK'))
    CREATE NONCLUSTERED INDEX IX_ALERTA_STOCK_Pendiente ON dbo.ALERTA_STOCK (atendida, id_insumo);

-- Un cajero solo puede tener UN turno abierto (índice único filtrado)
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UQ_TURNO_CAJA_Abierto' AND object_id = OBJECT_ID(N'dbo.TURNO_CAJA'))
BEGIN
    IF EXISTS (SELECT id_empleado FROM dbo.TURNO_CAJA WHERE fecha_cierre IS NULL GROUP BY id_empleado HAVING COUNT(*) > 1)
        PRINT N'AVISO: hay cajeros con más de un turno abierto; ciérrelos y vuelva a ejecutar para crear UQ_TURNO_CAJA_Abierto.';
    ELSE
        CREATE UNIQUE NONCLUSTERED INDEX UQ_TURNO_CAJA_Abierto ON dbo.TURNO_CAJA (id_empleado) WHERE fecha_cierre IS NULL;
END;

-- Una mesa solo puede tener UN pedido abierto: es la regla que impide el
-- pedido duplicado aunque dos meseros guarden al mismo tiempo.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UQ_PEDIDO_MesaAbierta' AND object_id = OBJECT_ID(N'dbo.PEDIDO'))
BEGIN
    IF EXISTS (SELECT id_mesa FROM dbo.PEDIDO WHERE estado IN ('ABIERTO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO') GROUP BY id_mesa HAVING COUNT(*) > 1)
        PRINT N'AVISO: hay mesas con más de un pedido abierto; corríjalas y vuelva a ejecutar para crear UQ_PEDIDO_MesaAbierta.';
    ELSE
        CREATE UNIQUE NONCLUSTERED INDEX UQ_PEDIDO_MesaAbierta ON dbo.PEDIDO (id_mesa)
            WHERE estado IN ('ABIERTO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO');
END;
GO

/* ============================================================================
   4. VISTAS
   ========================================================================== */
PRINT N'4/8 Vistas';
GO

CREATE OR ALTER VIEW dbo.vw_menu_activo AS
SELECT
    p.id_producto,
    p.nombre_producto,
    p.descripcion,
    p.precio,
    c.nombre_categoria,
    e.nombre_estacion
FROM dbo.PRODUCTO p
INNER JOIN dbo.CATEGORIA c ON p.id_categoria = c.id_categoria
INNER JOIN dbo.ESTACION e ON p.id_estacion = e.id_estacion
WHERE p.activo = 1;
GO

CREATE OR ALTER VIEW dbo.vw_mesas_estado AS
SELECT
    m.id_mesa,
    m.numero_mesa,
    m.capacidad,
    m.zona,
    m.estado AS estado_mesa,
    p.id_pedido,
    p.fecha_hora AS fecha_pedido,
    p.subtotal
FROM dbo.MESA m
LEFT JOIN dbo.PEDIDO p ON m.id_mesa = p.id_mesa AND p.estado IN ('ABIERTO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO');
GO

CREATE OR ALTER VIEW dbo.vw_cola_cocina AS
SELECT
    dp.id_pedido,
    m.numero_mesa,
    p.nombre_producto,
    dp.cantidad,
    dp.estado_cocina,
    dp.notas,
    e.nombre_estacion,
    dp.fecha_actualizacion
FROM dbo.DETALLE_PEDIDO dp
INNER JOIN dbo.PRODUCTO p ON dp.id_producto = p.id_producto
INNER JOIN dbo.ESTACION e ON p.id_estacion = e.id_estacion
INNER JOIN dbo.PEDIDO ped ON dp.id_pedido = ped.id_pedido
INNER JOIN dbo.MESA m ON ped.id_mesa = m.id_mesa
WHERE dp.estado_cocina IN ('PENDIENTE', 'PREPARANDO');
GO

CREATE OR ALTER VIEW dbo.vw_factura_ticket AS
SELECT
    f.numero_factura,
    f.fecha_hora,
    m.numero_mesa,
    emp.nombre + ' ' + emp.apellido AS cajero,
    f.subtotal,
    f.descuento,
    f.impuesto_ventas,
    f.servicio,
    f.total,
    f.vuelto,
    f.estado
FROM dbo.FACTURA f
INNER JOIN dbo.PEDIDO p ON f.id_pedido = p.id_pedido
INNER JOIN dbo.MESA m ON p.id_mesa = m.id_mesa
INNER JOIN dbo.EMPLEADO emp ON f.id_empleado = emp.id_empleado;
GO
/* ============================================================================
   5. TRIGGERS
   Todos siguen el mismo patrón: la lógica va dentro de BEGIN TRY; si algo
   falla (o el propio trigger decide rechazar la operación con THROW), el
   CATCH deshace la transacción completa y relanza el error a la aplicación.
   Todos soportan operaciones de varias filas (trabajan con inserted/deleted
   como conjuntos, nunca fila por fila).

   Resumen
     INSTEAD OF           trg_producto_baja_logica   (PRODUCTO, DELETE)
     INSTEAD OF           trg_factura_no_eliminar    (FACTURA, DELETE)
     AFTER 3 operaciones  trg_pedido_auditoria       (PEDIDO, INSERT/UPDATE/DELETE)
     AFTER 1 operación    trg_pedido_estado_final    (PEDIDO, UPDATE)
     AFTER 2 operaciones  trg_insumo_stock           (INSUMO, INSERT/UPDATE)
     AFTER 1 operación    trg_factura_inmutable      (FACTURA, UPDATE)
     AFTER 2 operaciones  trg_pago_inmutable         (PAGO, UPDATE/DELETE)
     AFTER 2 operaciones  trg_turno_caja_cerrado     (TURNO_CAJA, UPDATE/DELETE)
   ========================================================================== */
PRINT N'5/8 Triggers';
GO

-- Triggers que ya no existen (versiones anteriores del proyecto)
IF OBJECT_ID(N'dbo.trg_insumo_alerta_stock', N'TR') IS NOT NULL DROP TRIGGER dbo.trg_insumo_alerta_stock;
IF OBJECT_ID(N'dbo.trg_insumo_alerta_alta', N'TR') IS NOT NULL DROP TRIGGER dbo.trg_insumo_alerta_alta;
IF OBJECT_ID(N'dbo.trg_insumo_alertas_reposicion', N'TR') IS NOT NULL DROP TRIGGER dbo.trg_insumo_alertas_reposicion;
IF OBJECT_ID(N'dbo.trg_insumo_movimiento', N'TR') IS NOT NULL DROP TRIGGER dbo.trg_insumo_movimiento;
GO

/* ----------------------------------------------------------------------------
   trg_producto_baja_logica  (INSTEAD OF DELETE sobre PRODUCTO)
   Un producto del menú nunca se borra "a lo loco":
     - si cocina todavía lo está preparando, la baja se rechaza;
     - si nunca se vendió, sí se borra físicamente (y su receta, por el
       ON DELETE CASCADE);
     - si ya aparece en pedidos, se conserva el historial y solo se desactiva.
   La aplicación no tiene que distinguir los tres casos: siempre hace DELETE.
   -------------------------------------------------------------------------- */
CREATE OR ALTER TRIGGER dbo.trg_producto_baja_logica
ON dbo.PRODUCTO
INSTEAD OF DELETE
AS
BEGIN
    SET NOCOUNT ON;

    BEGIN TRY
        IF EXISTS (
            SELECT 1
            FROM dbo.DETALLE_PEDIDO dp
            INNER JOIN deleted d ON d.id_producto = dp.id_producto
            WHERE dp.estado_cocina IN ('PENDIENTE', 'PREPARANDO')
        )
            THROW 50016, 'No se puede dar de baja un producto que cocina todavía está preparando.', 1;

        -- Nunca vendido: borrado real (dentro de un INSTEAD OF, el DELETE sobre
        -- la misma tabla no vuelve a disparar este trigger).
        DELETE p
        FROM dbo.PRODUCTO p
        INNER JOIN deleted d ON d.id_producto = p.id_producto
        WHERE NOT EXISTS (SELECT 1 FROM dbo.DETALLE_PEDIDO dp WHERE dp.id_producto = p.id_producto);

        -- Con historial de ventas: baja lógica
        UPDATE p
        SET p.activo = 0
        FROM dbo.PRODUCTO p
        INNER JOIN deleted d ON d.id_producto = p.id_producto
        WHERE EXISTS (SELECT 1 FROM dbo.DETALLE_PEDIDO dp WHERE dp.id_producto = p.id_producto);
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

/* ----------------------------------------------------------------------------
   trg_factura_no_eliminar  (INSTEAD OF DELETE sobre FACTURA)
   Una factura emitida es un documento: no se borra, se anula.
   -------------------------------------------------------------------------- */
CREATE OR ALTER TRIGGER dbo.trg_factura_no_eliminar
ON dbo.FACTURA
INSTEAD OF DELETE
AS
BEGIN
    SET NOCOUNT ON;

    BEGIN TRY
        IF EXISTS (SELECT 1 FROM deleted)
            THROW 50010, 'Las facturas no se eliminan. Anule la factura en su lugar.', 1;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

/* ----------------------------------------------------------------------------
   trg_pedido_auditoria  (AFTER INSERT, UPDATE, DELETE sobre PEDIDO)
   Bitácora de todo lo que le pasa a un pedido. Registra al empleado real que
   hizo el cambio: la aplicación lo envía con sp_set_session_context y aquí se
   lee con SESSION_CONTEXT(). Si no viene (por ejemplo, cambios desde SSMS)
   queda la cuenta de conexión de SQL Server.
   -------------------------------------------------------------------------- */
CREATE OR ALTER TRIGGER dbo.trg_pedido_auditoria
ON dbo.PEDIDO
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;

    BEGIN TRY
        DECLARE @actor INT = TRY_CAST(SESSION_CONTEXT(N'id_empleado') AS INT);
        DECLARE @usuario VARCHAR(100) = COALESCE(CAST(SESSION_CONTEXT(N'usuario_app') AS VARCHAR(100)), SUSER_SNAME());

        -- INSERT: está en inserted y no en deleted
        INSERT INTO dbo.AUDITORIA_PEDIDO (id_pedido, accion, usuario_bd, id_empleado_app, estado_nuevo, subtotal_nuevo)
        SELECT i.id_pedido, 'INSERT', @usuario, COALESCE(@actor, i.id_empleado), i.estado, i.subtotal
        FROM inserted i
        WHERE NOT EXISTS (SELECT 1 FROM deleted d WHERE d.id_pedido = i.id_pedido);

        -- UPDATE: está en ambos
        INSERT INTO dbo.AUDITORIA_PEDIDO (id_pedido, accion, usuario_bd, id_empleado_app, estado_anterior, estado_nuevo, subtotal_anterior, subtotal_nuevo)
        SELECT i.id_pedido, 'UPDATE', @usuario, COALESCE(@actor, i.id_empleado), d.estado, i.estado, d.subtotal, i.subtotal
        FROM inserted i
        INNER JOIN deleted d ON i.id_pedido = d.id_pedido;

        -- DELETE: está en deleted y no en inserted
        INSERT INTO dbo.AUDITORIA_PEDIDO (id_pedido, accion, usuario_bd, id_empleado_app, estado_anterior, subtotal_anterior)
        SELECT d.id_pedido, 'DELETE', @usuario, COALESCE(@actor, d.id_empleado), d.estado, d.subtotal
        FROM deleted d
        WHERE NOT EXISTS (SELECT 1 FROM inserted i WHERE i.id_pedido = d.id_pedido);
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

/* ----------------------------------------------------------------------------
   trg_pedido_estado_final  (AFTER UPDATE sobre PEDIDO)
   Protege la máquina de estados del pedido:
     - ANULADO es definitivo;
     - nadie puede poner FACTURADO "a mano": tiene que existir una factura
       COBRADA (sp_cobrar_cuenta la inserta justo antes);
     - un pedido FACTURADO solo vuelve a LISTO si su factura fue anulada.
   -------------------------------------------------------------------------- */
CREATE OR ALTER TRIGGER dbo.trg_pedido_estado_final
ON dbo.PEDIDO
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    IF NOT UPDATE(estado)
        RETURN;

    BEGIN TRY
        IF EXISTS (
            SELECT 1
            FROM inserted i
            INNER JOIN deleted d ON d.id_pedido = i.id_pedido
            WHERE d.estado = 'ANULADO' AND i.estado <> d.estado
        )
            THROW 50013, 'Un pedido anulado no puede cambiar de estado.', 1;

        IF EXISTS (
            SELECT 1
            FROM inserted i
            INNER JOIN deleted d ON d.id_pedido = i.id_pedido
            WHERE d.estado = 'FACTURADO'
              AND i.estado <> 'FACTURADO'
              AND (i.estado <> 'LISTO'
                   OR EXISTS (SELECT 1 FROM dbo.FACTURA f WHERE f.id_pedido = i.id_pedido AND f.estado = 'COBRADA'))
        )
            THROW 50013, 'Un pedido facturado solo puede reabrirse si su factura fue anulada.', 1;

        IF EXISTS (
            SELECT 1
            FROM inserted i
            INNER JOIN deleted d ON d.id_pedido = i.id_pedido
            WHERE i.estado = 'FACTURADO'
              AND d.estado <> 'FACTURADO'
              AND NOT EXISTS (SELECT 1 FROM dbo.FACTURA f WHERE f.id_pedido = i.id_pedido AND f.estado = 'COBRADA')
        )
            THROW 50014, 'No se puede marcar un pedido como facturado sin una factura cobrada.', 1;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

/* ----------------------------------------------------------------------------
   trg_insumo_stock  (AFTER INSERT, UPDATE sobre INSUMO)
   Mantiene las alertas de inventario sin que nadie tenga que acordarse:
     - si un insumo queda en o por debajo del mínimo (al crearlo o al
       descontarlo) y no tiene ya una alerta pendiente, se crea una;
     - si se repone por encima del mínimo, sus alertas pendientes quedan
       atendidas solas.
   -------------------------------------------------------------------------- */
CREATE OR ALTER TRIGGER dbo.trg_insumo_stock
ON dbo.INSUMO
AFTER INSERT, UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    IF NOT (UPDATE(stock_actual) OR UPDATE(stock_minimo))
        RETURN;

    BEGIN TRY
        INSERT INTO dbo.ALERTA_STOCK (id_insumo, stock_actual, stock_minimo)
        SELECT i.id_insumo, i.stock_actual, i.stock_minimo
        FROM inserted i
        WHERE i.stock_actual <= i.stock_minimo
          AND NOT EXISTS (SELECT 1 FROM dbo.ALERTA_STOCK a WHERE a.id_insumo = i.id_insumo AND a.atendida = 0);

        UPDATE a
        SET a.atendida = 1
        FROM dbo.ALERTA_STOCK a
        INNER JOIN inserted i ON i.id_insumo = a.id_insumo
        WHERE a.atendida = 0
          AND i.stock_actual > i.stock_minimo;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

/* ----------------------------------------------------------------------------
   trg_factura_inmutable  (AFTER UPDATE sobre FACTURA)
   Los montos y las referencias de una factura emitida no se tocan; lo único
   permitido es anularla (y una factura anulada no se "revive").
   -------------------------------------------------------------------------- */
CREATE OR ALTER TRIGGER dbo.trg_factura_inmutable
ON dbo.FACTURA
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    BEGIN TRY
        IF EXISTS (
            SELECT 1
            FROM inserted i
            INNER JOIN deleted d ON d.id_factura = i.id_factura
            WHERE i.numero_factura <> d.numero_factura
               OR i.id_pedido <> d.id_pedido
               OR ISNULL(i.id_cliente, 0) <> ISNULL(d.id_cliente, 0)
               OR i.id_empleado <> d.id_empleado
               OR i.id_turno <> d.id_turno
               OR i.subtotal <> d.subtotal
               OR i.descuento <> d.descuento
               OR i.impuesto_ventas <> d.impuesto_ventas
               OR i.servicio <> d.servicio
               OR i.total <> d.total
               OR i.vuelto <> d.vuelto
               OR (d.estado = 'ANULADA' AND i.estado <> 'ANULADA')
        )
            THROW 50011, 'Los datos de una factura emitida no se pueden modificar. Solo se permite anularla.', 1;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

/* ----------------------------------------------------------------------------
   trg_pago_inmutable  (AFTER UPDATE, DELETE sobre PAGO)
   -------------------------------------------------------------------------- */
CREATE OR ALTER TRIGGER dbo.trg_pago_inmutable
ON dbo.PAGO
AFTER UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;

    BEGIN TRY
        IF EXISTS (SELECT 1 FROM deleted)
            THROW 50012, 'Los pagos registrados no se pueden modificar ni eliminar.', 1;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

/* ----------------------------------------------------------------------------
   trg_turno_caja_cerrado  (AFTER UPDATE, DELETE sobre TURNO_CAJA)
   Un turno que ya se cerró (tiene fecha_cierre) queda congelado.
   -------------------------------------------------------------------------- */
CREATE OR ALTER TRIGGER dbo.trg_turno_caja_cerrado
ON dbo.TURNO_CAJA
AFTER UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;

    BEGIN TRY
        IF EXISTS (SELECT 1 FROM deleted d WHERE d.fecha_cierre IS NOT NULL)
            THROW 50015, 'El turno ya está cerrado y no puede modificarse ni eliminarse.', 1;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO
/* ============================================================================
   6. PROCEDIMIENTOS BÁSICOS (CRUD)
   insertar / actualizar / eliminar / consultar para 11 de las 21 tablas:
   ROL, ESTACION, CATEGORIA, METODO_PAGO, MESA, CLIENTE, PRODUCTO, INSUMO,
   RECETA, EMPLEADO y TURNO_CAJA. Las otras 10 tablas no se editan "a mano":
   PEDIDO, DETALLE_PEDIDO, FACTURA y PAGO solo cambian por los procedimientos
   de negocio; MESERO/COCINERO/CAJERO acompañan a EMPLEADO; AUDITORIA_PEDIDO,
   ALERTA_STOCK y RESPALDO_BD las llenan triggers o procedimientos.

   Todos comparten la misma estructura:
     - validan los datos y lanzan THROW 50100/50101 con un mensaje claro;
     - abren su propia transacción solo si el llamador no tiene una abierta
       (así se pueden combinar varios dentro de una transacción de la
       aplicación, por ejemplo EMPLEADO + su subclase);
     - en el CATCH deshacen lo hecho y traducen los errores de SQL Server
       más comunes: 2601/2627 (valor repetido) -> 50103, 547 (registro
       relacionado o regla CHECK) -> 50102.
   El panel administrativo de la aplicación escribe únicamente a través de
   estos procedimientos.
   ========================================================================== */
PRINT N'6/8 Procedimientos básicos';
GO

-- --------------------------------------------------------------------------
-- ROL
-- --------------------------------------------------------------------------

CREATE OR ALTER PROCEDURE dbo.sp_rol_insertar
    @nombre_rol VARCHAR(50),
    @id_rol INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NULLIF(LTRIM(RTRIM(@nombre_rol)), '') IS NULL
            THROW 50100, 'El nombre del rol es obligatorio.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        INSERT INTO dbo.ROL (nombre_rol)
        VALUES (LTRIM(RTRIM(@nombre_rol)));

        SET @id_rol = SCOPE_IDENTITY();

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un rol con ese nombre.', 1;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del rol no cumplen las reglas de la tabla.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_rol_actualizar
    @id_rol INT,
    @nombre_rol VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.ROL WHERE id_rol = @id_rol)
            THROW 50101, 'El rol no existe.', 1;
        IF NULLIF(LTRIM(RTRIM(@nombre_rol)), '') IS NULL
            THROW 50100, 'El nombre del rol es obligatorio.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        UPDATE dbo.ROL
        SET nombre_rol = LTRIM(RTRIM(@nombre_rol))
        WHERE id_rol = @id_rol;

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un rol con ese nombre.', 1;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del rol no cumplen las reglas de la tabla.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_rol_eliminar
    @id_rol INT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.ROL WHERE id_rol = @id_rol)
            THROW 50101, 'El rol no existe.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        DELETE FROM dbo.ROL WHERE id_rol = @id_rol;

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar el rol: tiene empleados asociados.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_rol_consultar
    @id_rol INT = NULL            -- NULL: todos los registros
AS
BEGIN
    SET NOCOUNT ON;

    BEGIN TRY
        IF @id_rol IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.ROL WHERE id_rol = @id_rol)
            THROW 50101, 'El rol no existe.', 1;

        SELECT id_rol, nombre_rol
        FROM dbo.ROL
        WHERE @id_rol IS NULL OR id_rol = @id_rol
        ORDER BY nombre_rol;
    END TRY
    BEGIN CATCH
        THROW;
    END CATCH
END;
GO

-- --------------------------------------------------------------------------
-- ESTACION
-- --------------------------------------------------------------------------

CREATE OR ALTER PROCEDURE dbo.sp_estacion_insertar
    @nombre_estacion VARCHAR(50),
    @id_estacion INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NULLIF(LTRIM(RTRIM(@nombre_estacion)), '') IS NULL
            THROW 50100, 'El nombre de la estación es obligatorio.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        INSERT INTO dbo.ESTACION (nombre_estacion)
        VALUES (LTRIM(RTRIM(@nombre_estacion)));

        SET @id_estacion = SCOPE_IDENTITY();

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe una estación con ese nombre.', 1;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos de la estación no cumplen las reglas de la tabla.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_estacion_actualizar
    @id_estacion INT,
    @nombre_estacion VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.ESTACION WHERE id_estacion = @id_estacion)
            THROW 50101, 'La estación no existe.', 1;
        IF NULLIF(LTRIM(RTRIM(@nombre_estacion)), '') IS NULL
            THROW 50100, 'El nombre de la estación es obligatorio.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        UPDATE dbo.ESTACION
        SET nombre_estacion = LTRIM(RTRIM(@nombre_estacion))
        WHERE id_estacion = @id_estacion;

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe una estación con ese nombre.', 1;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos de la estación no cumplen las reglas de la tabla.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_estacion_eliminar
    @id_estacion INT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.ESTACION WHERE id_estacion = @id_estacion)
            THROW 50101, 'La estación no existe.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        DELETE FROM dbo.ESTACION WHERE id_estacion = @id_estacion;

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar la estación: tiene productos o cocineros asociados.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_estacion_consultar
    @id_estacion INT = NULL            -- NULL: todos los registros
AS
BEGIN
    SET NOCOUNT ON;

    BEGIN TRY
        IF @id_estacion IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.ESTACION WHERE id_estacion = @id_estacion)
            THROW 50101, 'La estación no existe.', 1;

        SELECT id_estacion, nombre_estacion
        FROM dbo.ESTACION
        WHERE @id_estacion IS NULL OR id_estacion = @id_estacion
        ORDER BY nombre_estacion;
    END TRY
    BEGIN CATCH
        THROW;
    END CATCH
END;
GO

-- --------------------------------------------------------------------------
-- CATEGORIA
-- --------------------------------------------------------------------------

CREATE OR ALTER PROCEDURE dbo.sp_categoria_insertar
    @nombre_categoria VARCHAR(50),
    @descripcion VARCHAR(200) = NULL,
    @id_categoria INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NULLIF(LTRIM(RTRIM(@nombre_categoria)), '') IS NULL
            THROW 50100, 'El nombre de la categoría es obligatorio.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        INSERT INTO dbo.CATEGORIA (nombre_categoria, descripcion)
        VALUES (LTRIM(RTRIM(@nombre_categoria)), NULLIF(LTRIM(RTRIM(@descripcion)), ''));

        SET @id_categoria = SCOPE_IDENTITY();

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe una categoría con ese nombre.', 1;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos de la categoría no cumplen las reglas de la tabla.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_categoria_actualizar
    @id_categoria INT,
    @nombre_categoria VARCHAR(50),
    @descripcion VARCHAR(200) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.CATEGORIA WHERE id_categoria = @id_categoria)
            THROW 50101, 'La categoría no existe.', 1;
        IF NULLIF(LTRIM(RTRIM(@nombre_categoria)), '') IS NULL
            THROW 50100, 'El nombre de la categoría es obligatorio.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        UPDATE dbo.CATEGORIA
        SET nombre_categoria = LTRIM(RTRIM(@nombre_categoria)),
            descripcion = NULLIF(LTRIM(RTRIM(@descripcion)), '')
        WHERE id_categoria = @id_categoria;

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe una categoría con ese nombre.', 1;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos de la categoría no cumplen las reglas de la tabla.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_categoria_eliminar
    @id_categoria INT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.CATEGORIA WHERE id_categoria = @id_categoria)
            THROW 50101, 'La categoría no existe.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        DELETE FROM dbo.CATEGORIA WHERE id_categoria = @id_categoria;

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar la categoría: tiene productos asociados.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_categoria_consultar
    @id_categoria INT = NULL            -- NULL: todos los registros
AS
BEGIN
    SET NOCOUNT ON;

    BEGIN TRY
        IF @id_categoria IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.CATEGORIA WHERE id_categoria = @id_categoria)
            THROW 50101, 'La categoría no existe.', 1;

        SELECT id_categoria, nombre_categoria, descripcion
        FROM dbo.CATEGORIA
        WHERE @id_categoria IS NULL OR id_categoria = @id_categoria
        ORDER BY nombre_categoria;
    END TRY
    BEGIN CATCH
        THROW;
    END CATCH
END;
GO

-- --------------------------------------------------------------------------
-- METODO_PAGO
-- --------------------------------------------------------------------------

CREATE OR ALTER PROCEDURE dbo.sp_metodo_pago_insertar
    @nombre_metodo VARCHAR(50),
    @id_metodo_pago INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NULLIF(LTRIM(RTRIM(@nombre_metodo)), '') IS NULL
            THROW 50100, 'El nombre del método de pago es obligatorio.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        INSERT INTO dbo.METODO_PAGO (nombre_metodo)
        VALUES (LTRIM(RTRIM(@nombre_metodo)));

        SET @id_metodo_pago = SCOPE_IDENTITY();

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un método de pago con ese nombre.', 1;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del método de pago no cumplen las reglas de la tabla.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_metodo_pago_actualizar
    @id_metodo_pago INT,
    @nombre_metodo VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.METODO_PAGO WHERE id_metodo_pago = @id_metodo_pago)
            THROW 50101, 'El método de pago no existe.', 1;
        IF NULLIF(LTRIM(RTRIM(@nombre_metodo)), '') IS NULL
            THROW 50100, 'El nombre del método de pago es obligatorio.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        UPDATE dbo.METODO_PAGO
        SET nombre_metodo = LTRIM(RTRIM(@nombre_metodo))
        WHERE id_metodo_pago = @id_metodo_pago;

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un método de pago con ese nombre.', 1;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del método de pago no cumplen las reglas de la tabla.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_metodo_pago_eliminar
    @id_metodo_pago INT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.METODO_PAGO WHERE id_metodo_pago = @id_metodo_pago)
            THROW 50101, 'El método de pago no existe.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        DELETE FROM dbo.METODO_PAGO WHERE id_metodo_pago = @id_metodo_pago;

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar el método de pago: tiene pagos asociados.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_metodo_pago_consultar
    @id_metodo_pago INT = NULL            -- NULL: todos los registros
AS
BEGIN
    SET NOCOUNT ON;

    BEGIN TRY
        IF @id_metodo_pago IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.METODO_PAGO WHERE id_metodo_pago = @id_metodo_pago)
            THROW 50101, 'El método de pago no existe.', 1;

        SELECT id_metodo_pago, nombre_metodo
        FROM dbo.METODO_PAGO
        WHERE @id_metodo_pago IS NULL OR id_metodo_pago = @id_metodo_pago
        ORDER BY nombre_metodo;
    END TRY
    BEGIN CATCH
        THROW;
    END CATCH
END;
GO

-- --------------------------------------------------------------------------
-- MESA
-- --------------------------------------------------------------------------

CREATE OR ALTER PROCEDURE dbo.sp_mesa_insertar
    @numero_mesa INT,
    @capacidad INT,
    @zona VARCHAR(50),
    @estado VARCHAR(20) = 'DISPONIBLE',
    @id_mesa INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF @numero_mesa IS NULL OR @numero_mesa < 1
            THROW 50100, 'El número de mesa debe ser mayor que cero.', 1;
        IF @capacidad IS NULL OR @capacidad < 1
            THROW 50100, 'La capacidad debe ser mayor que cero.', 1;
        IF NULLIF(LTRIM(RTRIM(@zona)), '') IS NULL
            THROW 50100, 'La zona de la mesa es obligatoria.', 1;
        IF @estado NOT IN ('DISPONIBLE', 'OCUPADA', 'RESERVADA', 'MANTENIMIENTO')
            THROW 50100, 'Estado de mesa no válido.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        INSERT INTO dbo.MESA (numero_mesa, capacidad, zona, estado)
        VALUES (@numero_mesa, @capacidad, LTRIM(RTRIM(@zona)), LTRIM(RTRIM(@estado)));

        SET @id_mesa = SCOPE_IDENTITY();

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe una mesa con ese número.', 1;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos de la mesa no cumplen las reglas de la tabla.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_mesa_actualizar
    @id_mesa INT,
    @numero_mesa INT,
    @capacidad INT,
    @zona VARCHAR(50),
    @estado VARCHAR(20) = 'DISPONIBLE'
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.MESA WHERE id_mesa = @id_mesa)
            THROW 50101, 'La mesa no existe.', 1;
        IF @numero_mesa IS NULL OR @numero_mesa < 1
            THROW 50100, 'El número de mesa debe ser mayor que cero.', 1;
        IF @capacidad IS NULL OR @capacidad < 1
            THROW 50100, 'La capacidad debe ser mayor que cero.', 1;
        IF NULLIF(LTRIM(RTRIM(@zona)), '') IS NULL
            THROW 50100, 'La zona de la mesa es obligatoria.', 1;
        IF @estado NOT IN ('DISPONIBLE', 'OCUPADA', 'RESERVADA', 'MANTENIMIENTO')
            THROW 50100, 'Estado de mesa no válido.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        UPDATE dbo.MESA
        SET numero_mesa = @numero_mesa,
            capacidad = @capacidad,
            zona = LTRIM(RTRIM(@zona)),
            estado = LTRIM(RTRIM(@estado))
        WHERE id_mesa = @id_mesa;

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe una mesa con ese número.', 1;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos de la mesa no cumplen las reglas de la tabla.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_mesa_eliminar
    @id_mesa INT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.MESA WHERE id_mesa = @id_mesa)
            THROW 50101, 'La mesa no existe.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        DELETE FROM dbo.MESA WHERE id_mesa = @id_mesa;

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar la mesa: tiene pedidos asociados. Puede ponerla en mantenimiento.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_mesa_consultar
    @id_mesa INT = NULL            -- NULL: todos los registros
AS
BEGIN
    SET NOCOUNT ON;

    BEGIN TRY
        IF @id_mesa IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.MESA WHERE id_mesa = @id_mesa)
            THROW 50101, 'La mesa no existe.', 1;

        SELECT id_mesa, numero_mesa, capacidad, zona, estado
        FROM dbo.MESA
        WHERE @id_mesa IS NULL OR id_mesa = @id_mesa
        ORDER BY numero_mesa;
    END TRY
    BEGIN CATCH
        THROW;
    END CATCH
END;
GO

-- --------------------------------------------------------------------------
-- CLIENTE
-- --------------------------------------------------------------------------

CREATE OR ALTER PROCEDURE dbo.sp_cliente_insertar
    @cedula_cifrada VARCHAR(512),
    @nombre VARCHAR(50),
    @apellido VARCHAR(50),
    @telefono VARCHAR(20) = NULL,
    @correo VARCHAR(100) = NULL,
    @id_cliente INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NULLIF(LTRIM(RTRIM(@cedula_cifrada)), '') IS NULL
            THROW 50100, 'La cédula del cliente es obligatoria.', 1;
        IF NULLIF(LTRIM(RTRIM(@nombre)), '') IS NULL OR NULLIF(LTRIM(RTRIM(@apellido)), '') IS NULL
            THROW 50100, 'Nombre y apellido del cliente son obligatorios.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        INSERT INTO dbo.CLIENTE (cedula, nombre, apellido, telefono, correo)
        VALUES (CONVERT(VARBINARY(256), @cedula_cifrada, 2), LTRIM(RTRIM(@nombre)), LTRIM(RTRIM(@apellido)), NULLIF(LTRIM(RTRIM(@telefono)), ''), NULLIF(LTRIM(RTRIM(@correo)), ''));

        SET @id_cliente = SCOPE_IDENTITY();

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un cliente con esos datos.', 1;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del cliente no cumplen las reglas de la tabla.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_cliente_actualizar
    @id_cliente INT,
    @cedula_cifrada VARCHAR(512) = NULL,
    @nombre VARCHAR(50),
    @apellido VARCHAR(50),
    @telefono VARCHAR(20) = NULL,
    @correo VARCHAR(100) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.CLIENTE WHERE id_cliente = @id_cliente)
            THROW 50101, 'El cliente no existe.', 1;
        IF NULLIF(LTRIM(RTRIM(@nombre)), '') IS NULL OR NULLIF(LTRIM(RTRIM(@apellido)), '') IS NULL
            THROW 50100, 'Nombre y apellido del cliente son obligatorios.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        UPDATE dbo.CLIENTE
        SET cedula = CASE WHEN @cedula_cifrada IS NULL THEN cedula ELSE CONVERT(VARBINARY(256), @cedula_cifrada, 2) END,
            nombre = LTRIM(RTRIM(@nombre)),
            apellido = LTRIM(RTRIM(@apellido)),
            telefono = NULLIF(LTRIM(RTRIM(@telefono)), ''),
            correo = NULLIF(LTRIM(RTRIM(@correo)), '')
        WHERE id_cliente = @id_cliente;

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un cliente con esos datos.', 1;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del cliente no cumplen las reglas de la tabla.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_cliente_eliminar
    @id_cliente INT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.CLIENTE WHERE id_cliente = @id_cliente)
            THROW 50101, 'El cliente no existe.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        DELETE FROM dbo.CLIENTE WHERE id_cliente = @id_cliente;

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar el cliente: tiene facturas asociadas.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_cliente_consultar
    @id_cliente INT = NULL            -- NULL: todos los registros
AS
BEGIN
    SET NOCOUNT ON;

    BEGIN TRY
        IF @id_cliente IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.CLIENTE WHERE id_cliente = @id_cliente)
            THROW 50101, 'El cliente no existe.', 1;

        SELECT id_cliente, cedula, nombre, apellido, telefono, correo
        FROM dbo.CLIENTE
        WHERE @id_cliente IS NULL OR id_cliente = @id_cliente
        ORDER BY apellido, nombre;
    END TRY
    BEGIN CATCH
        THROW;
    END CATCH
END;
GO

-- --------------------------------------------------------------------------
-- PRODUCTO
-- --------------------------------------------------------------------------

CREATE OR ALTER PROCEDURE dbo.sp_producto_insertar
    @nombre_producto VARCHAR(100),
    @descripcion VARCHAR(255) = NULL,
    @precio DECIMAL(10,2),
    @id_categoria INT,
    @id_estacion INT,
    @activo BIT = 1,
    @id_producto INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NULLIF(LTRIM(RTRIM(@nombre_producto)), '') IS NULL
            THROW 50100, 'El nombre del producto es obligatorio.', 1;
        IF @precio IS NULL OR @precio < 0
            THROW 50100, 'El precio no puede ser negativo.', 1;
        IF NOT EXISTS (SELECT 1 FROM dbo.CATEGORIA WHERE id_categoria = @id_categoria)
            THROW 50100, 'La categoría indicada no existe.', 1;
        IF NOT EXISTS (SELECT 1 FROM dbo.ESTACION WHERE id_estacion = @id_estacion)
            THROW 50100, 'La estación indicada no existe.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        INSERT INTO dbo.PRODUCTO (nombre_producto, descripcion, precio, id_categoria, id_estacion, activo)
        VALUES (LTRIM(RTRIM(@nombre_producto)), NULLIF(LTRIM(RTRIM(@descripcion)), ''), @precio, @id_categoria, @id_estacion, @activo);

        SET @id_producto = SCOPE_IDENTITY();

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un producto con ese nombre.', 1;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del producto no cumplen las reglas de la tabla.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_producto_actualizar
    @id_producto INT,
    @nombre_producto VARCHAR(100),
    @descripcion VARCHAR(255) = NULL,
    @precio DECIMAL(10,2),
    @id_categoria INT,
    @id_estacion INT,
    @activo BIT = 1
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.PRODUCTO WHERE id_producto = @id_producto)
            THROW 50101, 'El producto no existe.', 1;
        IF NULLIF(LTRIM(RTRIM(@nombre_producto)), '') IS NULL
            THROW 50100, 'El nombre del producto es obligatorio.', 1;
        IF @precio IS NULL OR @precio < 0
            THROW 50100, 'El precio no puede ser negativo.', 1;
        IF NOT EXISTS (SELECT 1 FROM dbo.CATEGORIA WHERE id_categoria = @id_categoria)
            THROW 50100, 'La categoría indicada no existe.', 1;
        IF NOT EXISTS (SELECT 1 FROM dbo.ESTACION WHERE id_estacion = @id_estacion)
            THROW 50100, 'La estación indicada no existe.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        UPDATE dbo.PRODUCTO
        SET nombre_producto = LTRIM(RTRIM(@nombre_producto)),
            descripcion = NULLIF(LTRIM(RTRIM(@descripcion)), ''),
            precio = @precio,
            id_categoria = @id_categoria,
            id_estacion = @id_estacion,
            activo = @activo
        WHERE id_producto = @id_producto;

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un producto con ese nombre.', 1;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del producto no cumplen las reglas de la tabla.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_producto_eliminar
    @id_producto INT,
    @desactivado BIT OUTPUT     -- 1 si el trigger lo desactivó en vez de borrarlo
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.PRODUCTO WHERE id_producto = @id_producto)
            THROW 50101, 'El producto no existe.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        -- El trigger INSTEAD OF decide: borrado real si nunca se vendió, baja lógica si tiene historial
        DELETE FROM dbo.PRODUCTO WHERE id_producto = @id_producto;

        SET @desactivado = CASE WHEN EXISTS (SELECT 1 FROM dbo.PRODUCTO WHERE id_producto = @id_producto) THEN 1 ELSE 0 END;

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar el producto: tiene registros relacionados.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_producto_consultar
    @id_producto INT = NULL            -- NULL: todos los registros
AS
BEGIN
    SET NOCOUNT ON;

    BEGIN TRY
        IF @id_producto IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.PRODUCTO WHERE id_producto = @id_producto)
            THROW 50101, 'El producto no existe.', 1;

        SELECT id_producto, nombre_producto, descripcion, precio, id_categoria, id_estacion, activo
        FROM dbo.PRODUCTO
        WHERE @id_producto IS NULL OR id_producto = @id_producto
        ORDER BY nombre_producto;
    END TRY
    BEGIN CATCH
        THROW;
    END CATCH
END;
GO

-- --------------------------------------------------------------------------
-- INSUMO
-- --------------------------------------------------------------------------

CREATE OR ALTER PROCEDURE dbo.sp_insumo_insertar
    @nombre_insumo VARCHAR(100),
    @unidad_medida VARCHAR(20),
    @stock_actual DECIMAL(10,2),
    @stock_minimo DECIMAL(10,2),
    @costo_unitario DECIMAL(10,2),
    @id_insumo INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NULLIF(LTRIM(RTRIM(@nombre_insumo)), '') IS NULL OR NULLIF(LTRIM(RTRIM(@unidad_medida)), '') IS NULL
            THROW 50100, 'Nombre y unidad de medida del insumo son obligatorios.', 1;
        IF @stock_actual IS NULL OR @stock_actual < 0 OR @stock_minimo IS NULL OR @stock_minimo < 0 OR @costo_unitario IS NULL OR @costo_unitario < 0
            THROW 50100, 'Stock y costo no pueden ser negativos.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        INSERT INTO dbo.INSUMO (nombre_insumo, unidad_medida, stock_actual, stock_minimo, costo_unitario)
        VALUES (LTRIM(RTRIM(@nombre_insumo)), LTRIM(RTRIM(@unidad_medida)), @stock_actual, @stock_minimo, @costo_unitario);

        SET @id_insumo = SCOPE_IDENTITY();

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un insumo con ese nombre.', 1;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del insumo no cumplen las reglas de la tabla.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_insumo_actualizar
    @id_insumo INT,
    @nombre_insumo VARCHAR(100),
    @unidad_medida VARCHAR(20),
    @stock_actual DECIMAL(10,2),
    @stock_minimo DECIMAL(10,2),
    @costo_unitario DECIMAL(10,2)
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.INSUMO WHERE id_insumo = @id_insumo)
            THROW 50101, 'El insumo no existe.', 1;
        IF NULLIF(LTRIM(RTRIM(@nombre_insumo)), '') IS NULL OR NULLIF(LTRIM(RTRIM(@unidad_medida)), '') IS NULL
            THROW 50100, 'Nombre y unidad de medida del insumo son obligatorios.', 1;
        IF @stock_actual IS NULL OR @stock_actual < 0 OR @stock_minimo IS NULL OR @stock_minimo < 0 OR @costo_unitario IS NULL OR @costo_unitario < 0
            THROW 50100, 'Stock y costo no pueden ser negativos.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        UPDATE dbo.INSUMO
        SET nombre_insumo = LTRIM(RTRIM(@nombre_insumo)),
            unidad_medida = LTRIM(RTRIM(@unidad_medida)),
            stock_actual = @stock_actual,
            stock_minimo = @stock_minimo,
            costo_unitario = @costo_unitario
        WHERE id_insumo = @id_insumo;

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un insumo con ese nombre.', 1;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del insumo no cumplen las reglas de la tabla.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_insumo_eliminar
    @id_insumo INT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.INSUMO WHERE id_insumo = @id_insumo)
            THROW 50101, 'El insumo no existe.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        DELETE FROM dbo.INSUMO WHERE id_insumo = @id_insumo;

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar el insumo: se usa en recetas o tiene alertas de stock.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_insumo_consultar
    @id_insumo INT = NULL            -- NULL: todos los registros
AS
BEGIN
    SET NOCOUNT ON;

    BEGIN TRY
        IF @id_insumo IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.INSUMO WHERE id_insumo = @id_insumo)
            THROW 50101, 'El insumo no existe.', 1;

        SELECT id_insumo, nombre_insumo, unidad_medida, stock_actual, stock_minimo, costo_unitario
        FROM dbo.INSUMO
        WHERE @id_insumo IS NULL OR id_insumo = @id_insumo
        ORDER BY nombre_insumo;
    END TRY
    BEGIN CATCH
        THROW;
    END CATCH
END;
GO

-- --------------------------------------------------------------------------
-- RECETA
-- --------------------------------------------------------------------------

CREATE OR ALTER PROCEDURE dbo.sp_receta_insertar
    @id_producto INT,
    @id_insumo INT,
    @cantidad DECIMAL(10,2)
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF @cantidad IS NULL OR @cantidad <= 0
            THROW 50100, 'La cantidad de la receta debe ser mayor que cero.', 1;
        IF NOT EXISTS (SELECT 1 FROM dbo.PRODUCTO WHERE id_producto = @id_producto)
            THROW 50100, 'El producto indicado no existe.', 1;
        IF NOT EXISTS (SELECT 1 FROM dbo.INSUMO WHERE id_insumo = @id_insumo)
            THROW 50100, 'El insumo indicado no existe.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        INSERT INTO dbo.RECETA (id_producto, id_insumo, cantidad)
        VALUES (@id_producto, @id_insumo, @cantidad);

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ese insumo ya está en la receta del producto.', 1;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos de la receta no cumplen las reglas de la tabla.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_receta_actualizar
    @id_producto INT,
    @id_insumo INT,
    @cantidad DECIMAL(10,2)
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.RECETA WHERE id_producto = @id_producto AND id_insumo = @id_insumo)
            THROW 50101, 'La línea de receta no existe.', 1;
        IF @cantidad IS NULL OR @cantidad <= 0
            THROW 50100, 'La cantidad de la receta debe ser mayor que cero.', 1;
        IF NOT EXISTS (SELECT 1 FROM dbo.PRODUCTO WHERE id_producto = @id_producto)
            THROW 50100, 'El producto indicado no existe.', 1;
        IF NOT EXISTS (SELECT 1 FROM dbo.INSUMO WHERE id_insumo = @id_insumo)
            THROW 50100, 'El insumo indicado no existe.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        UPDATE dbo.RECETA
        SET cantidad = @cantidad
        WHERE id_producto = @id_producto AND id_insumo = @id_insumo;

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ese insumo ya está en la receta del producto.', 1;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos de la receta no cumplen las reglas de la tabla.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_receta_eliminar
    @id_producto INT,
    @id_insumo INT = NULL        -- NULL: borra la receta completa del producto
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        -- Borrar una línea concreta exige que exista; borrar la receta completa
        -- de un producto sin receta no es un error (lo usa la pantalla al guardar).
        IF @id_insumo IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.RECETA WHERE id_producto = @id_producto AND id_insumo = @id_insumo)
            THROW 50101, 'La línea de receta indicada no existe.', 1;
        IF NOT EXISTS (SELECT 1 FROM dbo.PRODUCTO WHERE id_producto = @id_producto)
            THROW 50101, 'El producto indicado no existe.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        DELETE FROM dbo.RECETA
        WHERE id_producto = @id_producto AND (@id_insumo IS NULL OR id_insumo = @id_insumo);

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar la receta.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_receta_consultar
    @id_producto INT = NULL      -- NULL: todas las recetas
AS
BEGIN
    SET NOCOUNT ON;

    BEGIN TRY
        IF @id_producto IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.PRODUCTO WHERE id_producto = @id_producto)
            THROW 50101, 'El producto indicado no existe.', 1;

        SELECT r.id_producto, p.nombre_producto, r.id_insumo, i.nombre_insumo, i.unidad_medida, r.cantidad
        FROM dbo.RECETA r
        INNER JOIN dbo.PRODUCTO p ON p.id_producto = r.id_producto
        INNER JOIN dbo.INSUMO i ON i.id_insumo = r.id_insumo
        WHERE @id_producto IS NULL OR r.id_producto = @id_producto
        ORDER BY p.nombre_producto, i.nombre_insumo;
    END TRY
    BEGIN CATCH
        THROW;
    END CATCH
END;
GO

-- --------------------------------------------------------------------------
-- EMPLEADO
-- --------------------------------------------------------------------------

CREATE OR ALTER PROCEDURE dbo.sp_empleado_insertar
    @nombre VARCHAR(50),
    @apellido VARCHAR(50),
    @correo VARCHAR(100),
    @contrasena_hash VARCHAR(255),
    @id_rol INT,
    @activo BIT = 1,
    @id_empleado INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NULLIF(LTRIM(RTRIM(@contrasena_hash)), '') IS NULL
            THROW 50100, 'La contraseña (hash) es obligatoria.', 1;
        IF NULLIF(LTRIM(RTRIM(@nombre)), '') IS NULL OR NULLIF(LTRIM(RTRIM(@apellido)), '') IS NULL
            THROW 50100, 'Nombre y apellido del empleado son obligatorios.', 1;
        IF NULLIF(LTRIM(RTRIM(@correo)), '') IS NULL OR @correo NOT LIKE '%_@_%._%'
            THROW 50100, 'El correo del empleado no es válido.', 1;
        IF NOT EXISTS (SELECT 1 FROM dbo.ROL WHERE id_rol = @id_rol)
            THROW 50100, 'El rol indicado no existe.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        INSERT INTO dbo.EMPLEADO (nombre, apellido, correo, contrasena_hash, id_rol, activo)
        VALUES (LTRIM(RTRIM(@nombre)), LTRIM(RTRIM(@apellido)), LTRIM(RTRIM(@correo)), LTRIM(RTRIM(@contrasena_hash)), @id_rol, @activo);

        SET @id_empleado = SCOPE_IDENTITY();

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un empleado con ese correo.', 1;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del empleado no cumplen las reglas de la tabla.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_empleado_actualizar
    @id_empleado INT,
    @nombre VARCHAR(50),
    @apellido VARCHAR(50),
    @correo VARCHAR(100),
    @contrasena_hash VARCHAR(255) = NULL,
    @id_rol INT,
    @activo BIT = 1
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.EMPLEADO WHERE id_empleado = @id_empleado)
            THROW 50101, 'El empleado no existe.', 1;
        IF NULLIF(LTRIM(RTRIM(@nombre)), '') IS NULL OR NULLIF(LTRIM(RTRIM(@apellido)), '') IS NULL
            THROW 50100, 'Nombre y apellido del empleado son obligatorios.', 1;
        IF NULLIF(LTRIM(RTRIM(@correo)), '') IS NULL OR @correo NOT LIKE '%_@_%._%'
            THROW 50100, 'El correo del empleado no es válido.', 1;
        IF NOT EXISTS (SELECT 1 FROM dbo.ROL WHERE id_rol = @id_rol)
            THROW 50100, 'El rol indicado no existe.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        UPDATE dbo.EMPLEADO
        SET nombre = LTRIM(RTRIM(@nombre)),
            apellido = LTRIM(RTRIM(@apellido)),
            correo = LTRIM(RTRIM(@correo)),
            contrasena_hash = COALESCE(NULLIF(LTRIM(RTRIM(@contrasena_hash)), ''), contrasena_hash),
            id_rol = @id_rol,
            activo = @activo
        WHERE id_empleado = @id_empleado;

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un empleado con ese correo.', 1;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del empleado no cumplen las reglas de la tabla.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_empleado_eliminar
    @id_empleado INT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.EMPLEADO WHERE id_empleado = @id_empleado)
            THROW 50101, 'El empleado no existe.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        DELETE FROM dbo.EMPLEADO WHERE id_empleado = @id_empleado;

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar el empleado: tiene pedidos, turnos o facturas asociados. Puede desactivarlo.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_empleado_consultar
    @id_empleado INT = NULL            -- NULL: todos los registros
AS
BEGIN
    SET NOCOUNT ON;

    BEGIN TRY
        IF @id_empleado IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.EMPLEADO WHERE id_empleado = @id_empleado)
            THROW 50101, 'El empleado no existe.', 1;

        SELECT id_empleado, nombre, apellido, correo, id_rol, fecha_ingreso, activo
        FROM dbo.EMPLEADO
        WHERE @id_empleado IS NULL OR id_empleado = @id_empleado
        ORDER BY apellido, nombre;
    END TRY
    BEGIN CATCH
        THROW;
    END CATCH
END;
GO

-- --------------------------------------------------------------------------
-- TURNO_CAJA
-- --------------------------------------------------------------------------

CREATE OR ALTER PROCEDURE dbo.sp_turno_caja_insertar
    @id_empleado INT,
    @monto_inicial DECIMAL(10,2),
    @id_turno INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF @monto_inicial IS NULL OR @monto_inicial < 0
            THROW 50100, 'El monto inicial no es válido.', 1;
        IF NOT EXISTS (SELECT 1 FROM dbo.CAJERO c INNER JOIN dbo.EMPLEADO e ON e.id_empleado = c.id_empleado WHERE c.id_empleado = @id_empleado AND e.activo = 1)
            THROW 50100, 'El empleado no es un cajero activo.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        INSERT INTO dbo.TURNO_CAJA (id_empleado, monto_inicial)
        VALUES (@id_empleado, @monto_inicial);

        SET @id_turno = SCOPE_IDENTITY();

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ese cajero ya tiene un turno abierto.', 1;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del turno no cumplen las reglas de la tabla.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_turno_caja_actualizar
    @id_turno INT,
    @id_empleado INT,
    @monto_inicial DECIMAL(10,2),
    @fecha_cierre DATETIME = NULL,
    @monto_final DECIMAL(10,2) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.TURNO_CAJA WHERE id_turno = @id_turno)
            THROW 50101, 'El turno de caja no existe.', 1;
        IF @fecha_cierre IS NOT NULL AND @monto_final IS NULL
            THROW 50100, 'Para cerrar el turno debe indicar el monto final.', 1;
        IF @monto_inicial IS NULL OR @monto_inicial < 0
            THROW 50100, 'El monto inicial no es válido.', 1;
        IF NOT EXISTS (SELECT 1 FROM dbo.CAJERO c INNER JOIN dbo.EMPLEADO e ON e.id_empleado = c.id_empleado WHERE c.id_empleado = @id_empleado AND e.activo = 1)
            THROW 50100, 'El empleado no es un cajero activo.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        UPDATE dbo.TURNO_CAJA
        SET id_empleado = @id_empleado,
            monto_inicial = @monto_inicial,
            fecha_cierre = @fecha_cierre,
            monto_final = @monto_final
        WHERE id_turno = @id_turno;

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ese cajero ya tiene un turno abierto.', 1;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del turno no cumplen las reglas de la tabla.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_turno_caja_eliminar
    @id_turno INT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- Si el llamador ya abrió una transacción, nos sumamos a ella
    DECLARE @tran_propia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.TURNO_CAJA WHERE id_turno = @id_turno)
            THROW 50101, 'El turno de caja no existe.', 1;

        IF @tran_propia = 1 BEGIN TRANSACTION;

        DELETE FROM dbo.TURNO_CAJA WHERE id_turno = @id_turno;

        IF @tran_propia = 1 COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @tran_propia = 1 AND @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar el turno: tiene facturas asociadas.', 1;
        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_turno_caja_consultar
    @id_turno INT = NULL            -- NULL: todos los registros
AS
BEGIN
    SET NOCOUNT ON;

    BEGIN TRY
        IF @id_turno IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.TURNO_CAJA WHERE id_turno = @id_turno)
            THROW 50101, 'El turno de caja no existe.', 1;

        SELECT id_turno, id_empleado, monto_inicial, fecha_cierre, monto_final
        FROM dbo.TURNO_CAJA
        WHERE @id_turno IS NULL OR id_turno = @id_turno
        ORDER BY fecha_apertura DESC;
    END TRY
    BEGIN CATCH
        THROW;
    END CATCH
END;
GO

/* ============================================================================
   7. PROCEDIMIENTOS DE NEGOCIO, REPORTE Y RESPALDOS
   Estos son los que hacen el trabajo "de verdad" del restaurante. Cada uno
   toca varias tablas dentro de una sola transacción, bloquea las filas que
   va a modificar (UPDLOCK, HOLDLOCK) para que dos cajas o dos meseros no se
   pisen, y devuelve errores con mensajes que la aplicación muestra tal cual.

     sp_registrar_pedido       mesero guarda una comanda nueva
     sp_agregar_items_pedido   mesero agrega una ronda a una mesa ya abierta
     sp_cobrar_cuenta          caja factura un pedido y registra los pagos
     sp_anular_pedido          se anula un pedido y vuelve el inventario
     sp_reporte_ventas         reporte por día / producto / mesero / pago
     sp_respaldar_bd           respaldo .bak verificado desde la aplicación
     sp_verificar_respaldo     vuelve a verificar un respaldo del historial
   ========================================================================== */
PRINT N'7/8 Procedimientos de negocio, reporte y respaldos';
GO

-- Procedimiento de versiones anteriores, sustituido por sp_turno_caja_insertar
IF OBJECT_ID(N'dbo.sp_abrir_turno', N'P') IS NOT NULL DROP PROCEDURE dbo.sp_abrir_turno;
GO

/* ----------------------------------------------------------------------------
   sp_registrar_pedido
   Recibe las líneas como JSON: [{"num_linea":1,"id_producto":3,"cantidad":2,"notas":"sin cebolla"}, ...]
   Idempotente: si el mismo token ya se guardó (doble clic, reintento del
   navegador, mala conexión) devuelve el pedido existente y no crea otro.
   -------------------------------------------------------------------------- */
CREATE OR ALTER PROCEDURE dbo.sp_registrar_pedido
    @token_idempotencia VARCHAR(100),
    @id_mesa INT,
    @id_empleado INT,
    @observaciones VARCHAR(255),
    @json_lineas NVARCHAR(MAX),
    @nuevo_id_pedido INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @lineas TABLE (
        num_linea INT NULL,
        id_producto INT NULL,
        cantidad INT NULL,
        notas VARCHAR(200) NULL
    );
    DECLARE @requerido TABLE (
        id_insumo INT PRIMARY KEY,
        cantidad DECIMAL(14,2) NOT NULL
    );
    DECLARE @estado_mesa VARCHAR(20);
    DECLARE @faltante VARCHAR(100);
    DECLARE @bloqueados INT;
    DECLARE @mensaje NVARCHAR(300);

    BEGIN TRY
        -- 1. Idempotencia: ¿ya se guardó esta misma solicitud?
        SELECT @nuevo_id_pedido = id_pedido
        FROM dbo.PEDIDO
        WHERE token_idempotencia = @token_idempotencia;

        IF @nuevo_id_pedido IS NOT NULL
            RETURN 1;

        -- 2. Validaciones de los datos recibidos
        IF @token_idempotencia IS NULL OR LEN(@token_idempotencia) = 0
            THROW 50040, 'Falta el identificador de la solicitud (token).', 1;

        IF ISJSON(ISNULL(@json_lineas, N'')) = 0
            THROW 50041, 'Las líneas del pedido no tienen un formato válido.', 1;

        INSERT INTO @lineas (num_linea, id_producto, cantidad, notas)
        SELECT
            TRY_CAST(JSON_VALUE(value, '$.num_linea') AS INT),
            TRY_CAST(JSON_VALUE(value, '$.id_producto') AS INT),
            TRY_CAST(JSON_VALUE(value, '$.cantidad') AS INT),
            NULLIF(LTRIM(RTRIM(LEFT(JSON_VALUE(value, '$.notas'), 200))), '')
        FROM OPENJSON(@json_lineas);

        IF NOT EXISTS (SELECT 1 FROM @lineas)
            THROW 50042, 'El pedido debe tener al menos un producto.', 1;

        IF EXISTS (SELECT 1 FROM @lineas WHERE num_linea IS NULL OR num_linea < 1 OR cantidad IS NULL OR cantidad < 1)
            THROW 50043, 'Cada línea debe tener un número de línea y una cantidad mayor que cero.', 1;

        IF EXISTS (SELECT num_linea FROM @lineas GROUP BY num_linea HAVING COUNT(*) > 1)
            THROW 50044, 'Hay números de línea repetidos en el pedido.', 1;

        IF EXISTS (
            SELECT 1
            FROM @lineas l
            LEFT JOIN dbo.PRODUCTO p ON p.id_producto = l.id_producto AND p.activo = 1
            WHERE p.id_producto IS NULL
        )
            THROW 50045, 'Uno de los productos no existe o ya no está disponible.', 1;

        IF NOT EXISTS (SELECT 1 FROM dbo.EMPLEADO WHERE id_empleado = @id_empleado AND activo = 1)
            THROW 50046, 'El empleado que registra el pedido no existe o está inactivo.', 1;

        -- 3. Transacción: mesa, inventario, pedido y detalle, todo o nada
        BEGIN TRANSACTION;

        SELECT @estado_mesa = estado
        FROM dbo.MESA WITH (UPDLOCK, HOLDLOCK)      -- nadie más toca esta mesa hasta el COMMIT
        WHERE id_mesa = @id_mesa;

        IF @estado_mesa IS NULL
            THROW 50001, 'La mesa especificada no existe.', 1;

        IF @estado_mesa = 'MANTENIMIENTO'
            THROW 50047, 'La mesa está en mantenimiento.', 1;

        -- Inventario necesario, agrupado por insumo (dos platos pueden compartir uno)
        INSERT INTO @requerido (id_insumo, cantidad)
        SELECT r.id_insumo, SUM(l.cantidad * r.cantidad)
        FROM @lineas l
        INNER JOIN dbo.RECETA r ON r.id_producto = l.id_producto
        GROUP BY r.id_insumo;

        SELECT @bloqueados = COUNT(*)
        FROM dbo.INSUMO i WITH (UPDLOCK, HOLDLOCK)  -- bloquea los insumos antes de comparar
        INNER JOIN @requerido q ON q.id_insumo = i.id_insumo;

        SELECT TOP (1) @faltante = i.nombre_insumo
        FROM dbo.INSUMO i
        INNER JOIN @requerido q ON q.id_insumo = i.id_insumo
        WHERE i.stock_actual < q.cantidad
        ORDER BY i.nombre_insumo;

        IF @faltante IS NOT NULL
        BEGIN
            SET @mensaje = CONCAT(N'Stock insuficiente de ', @faltante, N' para completar el pedido.');
            THROW 50002, @mensaje, 1;
        END

        INSERT INTO dbo.PEDIDO (token_idempotencia, id_mesa, id_empleado, observaciones)
        VALUES (@token_idempotencia, @id_mesa, @id_empleado, @observaciones);

        SET @nuevo_id_pedido = SCOPE_IDENTITY();

        INSERT INTO dbo.DETALLE_PEDIDO (id_pedido, num_linea, id_producto, cantidad, precio_unitario, notas)
        SELECT @nuevo_id_pedido, l.num_linea, l.id_producto, l.cantidad, p.precio, l.notas
        FROM @lineas l
        INNER JOIN dbo.PRODUCTO p ON p.id_producto = l.id_producto;

        UPDATE i
        SET i.stock_actual = i.stock_actual - q.cantidad
        FROM dbo.INSUMO i
        INNER JOIN @requerido q ON q.id_insumo = i.id_insumo;

        UPDATE dbo.PEDIDO
        SET subtotal = (
            SELECT ISNULL(SUM(cantidad * precio_unitario), 0)
            FROM dbo.DETALLE_PEDIDO
            WHERE id_pedido = @nuevo_id_pedido
        )
        WHERE id_pedido = @nuevo_id_pedido;

        UPDATE dbo.MESA SET estado = 'OCUPADA' WHERE id_mesa = @id_mesa;

        COMMIT TRANSACTION;
        RETURN 0;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;

        IF ERROR_NUMBER() IN (2601, 2627)
        BEGIN
            -- Caso 1: la misma solicitud llegó dos veces a la vez (doble clic) y
            -- la otra copia ganó. Devolvemos su pedido como si fuera la primera.
            SELECT @nuevo_id_pedido = id_pedido
            FROM dbo.PEDIDO
            WHERE token_idempotencia = @token_idempotencia;

            IF @nuevo_id_pedido IS NOT NULL
                RETURN 1;

            -- Caso 2: dos meseros distintos guardaron la misma mesa en el mismo
            -- instante; el índice único UQ_PEDIDO_MesaAbierta dejó pasar solo a uno.
            THROW 50007, 'Esa mesa ya tiene un pedido abierto. Agregue los productos al pedido existente.', 1;
        END;

        THROW;
    END CATCH
END;
GO

/* ----------------------------------------------------------------------------
   sp_agregar_items_pedido
   Agrega una ronda a un pedido abierto: numera las líneas a continuación de
   las que ya hay, descuenta inventario, recalcula el subtotal y vuelve a
   poner el pedido en ABIERTO para que cocina vea la ronda nueva.
   -------------------------------------------------------------------------- */
CREATE OR ALTER PROCEDURE dbo.sp_agregar_items_pedido
    @id_pedido INT,
    @json_lineas NVARCHAR(MAX)
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @lineas TABLE (
        orden INT NOT NULL,
        id_producto INT NULL,
        cantidad INT NULL,
        notas VARCHAR(200) NULL,
        num_linea INT NULL
    );
    DECLARE @requerido TABLE (
        id_insumo INT PRIMARY KEY,
        cantidad DECIMAL(14,2) NOT NULL
    );
    DECLARE @estado VARCHAR(20);
    DECLARE @ultima_linea INT;
    DECLARE @faltante VARCHAR(100);
    DECLARE @bloqueados INT;
    DECLARE @mensaje NVARCHAR(300);

    BEGIN TRY
        IF ISJSON(ISNULL(@json_lineas, N'')) = 0
            THROW 50041, 'Las líneas del pedido no tienen un formato válido.', 1;

        INSERT INTO @lineas (orden, id_producto, cantidad, notas)
        SELECT
            CAST([key] AS INT),
            TRY_CAST(JSON_VALUE(value, '$.id_producto') AS INT),
            TRY_CAST(JSON_VALUE(value, '$.cantidad') AS INT),
            NULLIF(LTRIM(RTRIM(LEFT(JSON_VALUE(value, '$.notas'), 200))), '')
        FROM OPENJSON(@json_lineas);

        IF NOT EXISTS (SELECT 1 FROM @lineas)
            THROW 50042, 'El pedido debe tener al menos un producto.', 1;

        IF EXISTS (SELECT 1 FROM @lineas WHERE cantidad IS NULL OR cantidad < 1)
            THROW 50043, 'Cada línea debe tener una cantidad mayor que cero.', 1;

        IF EXISTS (
            SELECT 1
            FROM @lineas l
            LEFT JOIN dbo.PRODUCTO p ON p.id_producto = l.id_producto AND p.activo = 1
            WHERE p.id_producto IS NULL
        )
            THROW 50045, 'Uno de los productos no existe o ya no está disponible.', 1;

        BEGIN TRANSACTION;

        SELECT @estado = estado
        FROM dbo.PEDIDO WITH (UPDLOCK, HOLDLOCK)
        WHERE id_pedido = @id_pedido;

        IF @estado IS NULL OR @estado NOT IN ('ABIERTO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO')
            THROW 50048, 'El pedido no existe o ya fue facturado o anulado.', 1;

        SELECT @ultima_linea = ISNULL(MAX(num_linea), 0)
        FROM dbo.DETALLE_PEDIDO
        WHERE id_pedido = @id_pedido;

        UPDATE l
        SET l.num_linea = @ultima_linea + n.posicion
        FROM @lineas l
        INNER JOIN (SELECT orden, ROW_NUMBER() OVER (ORDER BY orden) AS posicion FROM @lineas) n ON n.orden = l.orden;

        INSERT INTO @requerido (id_insumo, cantidad)
        SELECT r.id_insumo, SUM(l.cantidad * r.cantidad)
        FROM @lineas l
        INNER JOIN dbo.RECETA r ON r.id_producto = l.id_producto
        GROUP BY r.id_insumo;

        SELECT @bloqueados = COUNT(*)
        FROM dbo.INSUMO i WITH (UPDLOCK, HOLDLOCK)
        INNER JOIN @requerido q ON q.id_insumo = i.id_insumo;

        SELECT TOP (1) @faltante = i.nombre_insumo
        FROM dbo.INSUMO i
        INNER JOIN @requerido q ON q.id_insumo = i.id_insumo
        WHERE i.stock_actual < q.cantidad
        ORDER BY i.nombre_insumo;

        IF @faltante IS NOT NULL
        BEGIN
            SET @mensaje = CONCAT(N'Stock insuficiente de ', @faltante, N' para agregar los productos.');
            THROW 50002, @mensaje, 1;
        END

        INSERT INTO dbo.DETALLE_PEDIDO (id_pedido, num_linea, id_producto, cantidad, precio_unitario, notas)
        SELECT @id_pedido, l.num_linea, l.id_producto, l.cantidad, p.precio, l.notas
        FROM @lineas l
        INNER JOIN dbo.PRODUCTO p ON p.id_producto = l.id_producto;

        UPDATE i
        SET i.stock_actual = i.stock_actual - q.cantidad
        FROM dbo.INSUMO i
        INNER JOIN @requerido q ON q.id_insumo = i.id_insumo;

        UPDATE dbo.PEDIDO
        SET subtotal = (
                SELECT ISNULL(SUM(cantidad * precio_unitario), 0)
                FROM dbo.DETALLE_PEDIDO
                WHERE id_pedido = @id_pedido
            ),
            estado = 'ABIERTO'
        WHERE id_pedido = @id_pedido;

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

/* ----------------------------------------------------------------------------
   sp_cobrar_cuenta
   Factura un pedido: aplica descuento (porcentaje), IVA 13 % y servicio 10 %,
   registra uno o varios pagos (JSON: [{"id_metodo_pago":1,"monto":5000,"referencia":null}]),
   calcula el vuelto, marca el pedido FACTURADO y libera la mesa.
   -------------------------------------------------------------------------- */
CREATE OR ALTER PROCEDURE dbo.sp_cobrar_cuenta
    @id_pedido INT,
    @id_cliente INT = NULL,
    @id_cajero INT,
    @descuento DECIMAL(10,2) = 0,
    @json_pagos NVARCHAR(MAX),
    @numero_factura_out VARCHAR(50) OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @pagos TABLE (
        id_metodo_pago INT NULL,
        monto DECIMAL(10,2) NULL,
        referencia VARCHAR(100) NULL
    );
    DECLARE @subtotal DECIMAL(10,2), @id_mesa INT, @id_turno INT;
    DECLARE @monto_descuento DECIMAL(10,2), @base_imponible DECIMAL(10,2);
    DECLARE @iva DECIMAL(10,2), @servicio DECIMAL(10,2), @total DECIMAL(10,2);
    DECLARE @total_pagado DECIMAL(12,2), @vuelto DECIMAL(10,2), @id_factura INT;

    BEGIN TRY
        SET @descuento = ISNULL(@descuento, 0);

        IF @descuento < 0 OR @descuento > 100
            THROW 50049, 'El descuento debe estar entre 0 y 100 %.', 1;

        IF ISJSON(ISNULL(@json_pagos, N'')) = 0
            THROW 50050, 'Los pagos no tienen un formato válido.', 1;

        INSERT INTO @pagos (id_metodo_pago, monto, referencia)
        SELECT
            TRY_CAST(JSON_VALUE(value, '$.id_metodo_pago') AS INT),
            TRY_CAST(JSON_VALUE(value, '$.monto') AS DECIMAL(10,2)),
            NULLIF(LTRIM(RTRIM(LEFT(JSON_VALUE(value, '$.referencia'), 100))), '')
        FROM OPENJSON(@json_pagos);

        IF NOT EXISTS (SELECT 1 FROM @pagos)
            THROW 50051, 'Debe registrar al menos un pago.', 1;

        IF EXISTS (SELECT 1 FROM @pagos WHERE monto IS NULL OR monto <= 0)
            THROW 50052, 'Cada pago debe tener un monto mayor que cero.', 1;

        IF EXISTS (
            SELECT 1
            FROM @pagos pg
            LEFT JOIN dbo.METODO_PAGO mp ON mp.id_metodo_pago = pg.id_metodo_pago
            WHERE mp.id_metodo_pago IS NULL
        )
            THROW 50053, 'Uno de los métodos de pago no existe.', 1;

        IF @id_cliente IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.CLIENTE WHERE id_cliente = @id_cliente)
            THROW 50054, 'El cliente indicado no existe.', 1;

        BEGIN TRANSACTION;

        -- El pedido queda bloqueado: si dos cajas intentan cobrarlo, la segunda
        -- espera y luego recibe "ya fue facturado".
        SELECT @subtotal = subtotal, @id_mesa = id_mesa
        FROM dbo.PEDIDO WITH (UPDLOCK, HOLDLOCK)
        WHERE id_pedido = @id_pedido AND estado IN ('ABIERTO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO');

        IF @subtotal IS NULL
            THROW 50003, 'El pedido no existe o ya fue facturado/anulado.', 1;

        SELECT @id_turno = id_turno
        FROM dbo.TURNO_CAJA WITH (UPDLOCK, HOLDLOCK)
        WHERE id_empleado = @id_cajero AND fecha_cierre IS NULL;

        IF @id_turno IS NULL
            THROW 50004, 'El cajero no tiene un turno de caja abierto.', 1;

        SET @monto_descuento = @subtotal * (@descuento / 100.00);
        SET @base_imponible = @subtotal - @monto_descuento;
        SET @iva = @base_imponible * 0.13;
        SET @servicio = @base_imponible * 0.10;
        SET @total = @base_imponible + @iva + @servicio;

        SELECT @total_pagado = SUM(monto) FROM @pagos;

        IF @total_pagado < @total
            THROW 50005, 'El monto acumulado de pagos es inferior al total de la factura.', 1;

        SET @vuelto = @total_pagado - @total;

        SET @numero_factura_out = 'FAC-' + RIGHT('00000000' + CAST(@id_pedido AS VARCHAR(8)), 8);

        INSERT INTO dbo.FACTURA (numero_factura, id_pedido, id_cliente, id_empleado, id_turno, subtotal, descuento, impuesto_ventas, servicio, total, vuelto)
        VALUES (@numero_factura_out, @id_pedido, @id_cliente, @id_cajero, @id_turno, @subtotal, @monto_descuento, @iva, @servicio, @total, @vuelto);

        SET @id_factura = SCOPE_IDENTITY();

        INSERT INTO dbo.PAGO (id_factura, id_metodo_pago, monto, referencia)
        SELECT @id_factura, id_metodo_pago, monto, referencia
        FROM @pagos;

        -- La factura ya existe, así que trg_pedido_estado_final permite el cambio
        UPDATE dbo.PEDIDO SET estado = 'FACTURADO' WHERE id_pedido = @id_pedido;
        UPDATE dbo.MESA SET estado = 'DISPONIBLE' WHERE id_mesa = @id_mesa;

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

/* ----------------------------------------------------------------------------
   sp_anular_pedido
   Solo se anula lo que no está facturado. Devuelve al inventario los insumos
   de las líneas que cocina todavía no había empezado (PENDIENTE).
   -------------------------------------------------------------------------- */
CREATE OR ALTER PROCEDURE dbo.sp_anular_pedido
    @id_pedido INT,
    @id_empleado INT,
    @motivo VARCHAR(255)
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @id_mesa INT, @estado_pedido VARCHAR(20);

    BEGIN TRY
        IF @motivo IS NULL OR LEN(LTRIM(RTRIM(@motivo))) = 0
            THROW 50055, 'Debe indicar el motivo de la anulación.', 1;

        IF NOT EXISTS (SELECT 1 FROM dbo.EMPLEADO WHERE id_empleado = @id_empleado AND activo = 1)
            THROW 50046, 'El empleado que anula no existe o está inactivo.', 1;

        BEGIN TRANSACTION;

        SELECT @id_mesa = id_mesa, @estado_pedido = estado
        FROM dbo.PEDIDO WITH (UPDLOCK, HOLDLOCK)
        WHERE id_pedido = @id_pedido;

        IF @estado_pedido IS NULL OR @estado_pedido IN ('FACTURADO', 'ANULADO')
            THROW 50006, 'No se puede anular un pedido inexistente, facturado o ya anulado.', 1;

        UPDATE i
        SET i.stock_actual = i.stock_actual + q.cantidad
        FROM dbo.INSUMO i
        INNER JOIN (
            SELECT r.id_insumo, SUM(dp.cantidad * r.cantidad) AS cantidad
            FROM dbo.DETALLE_PEDIDO dp
            INNER JOIN dbo.RECETA r ON r.id_producto = dp.id_producto
            WHERE dp.id_pedido = @id_pedido AND dp.estado_cocina = 'PENDIENTE'
            GROUP BY r.id_insumo
        ) q ON q.id_insumo = i.id_insumo;

        UPDATE dbo.PEDIDO
        SET estado = 'ANULADO',
            anulado_por = @id_empleado,
            motivo_anulacion = LTRIM(RTRIM(@motivo))
        WHERE id_pedido = @id_pedido;

        UPDATE dbo.DETALLE_PEDIDO
        SET estado_cocina = 'CANCELADO',
            fecha_actualizacion = GETDATE()
        WHERE id_pedido = @id_pedido AND estado_cocina = 'PENDIENTE';

        UPDATE dbo.MESA SET estado = 'DISPONIBLE' WHERE id_mesa = @id_mesa;

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

/* ----------------------------------------------------------------------------
   sp_reporte_ventas  (procedimiento de reporte)
   Solo lectura. Usa aislamiento SNAPSHOT: lee una "foto" consistente de las
   facturas sin bloquear a la caja que sigue cobrando.
   -------------------------------------------------------------------------- */
CREATE OR ALTER PROCEDURE dbo.sp_reporte_ventas
    @fecha_inicio DATETIME,
    @fecha_fin DATETIME,
    @agrupar_por VARCHAR(20)        -- DIA | PRODUCTO | MESERO | PAGO
AS
BEGIN
    SET NOCOUNT ON;

    BEGIN TRY
        IF @fecha_inicio IS NULL OR @fecha_fin IS NULL OR @fecha_fin < @fecha_inicio
            THROW 50056, 'El rango de fechas del reporte no es válido.', 1;

        IF @agrupar_por NOT IN ('DIA', 'PRODUCTO', 'MESERO', 'PAGO')
            THROW 50057, 'Agrupación no válida. Use DIA, PRODUCTO, MESERO o PAGO.', 1;

        SET TRANSACTION ISOLATION LEVEL SNAPSHOT;

        IF @agrupar_por = 'DIA'
        BEGIN
            SELECT
                CAST(f.fecha_hora AS DATE) AS Fecha,
                COUNT(f.id_factura) AS Total_Facturas,
                SUM(f.subtotal) AS Subtotal_Acumulado,
                SUM(f.impuesto_ventas) AS IVA_Acumulado,
                SUM(f.servicio) AS Servicio_Acumulado,
                SUM(f.total) AS Venta_Total
            FROM dbo.FACTURA f
            WHERE f.fecha_hora BETWEEN @fecha_inicio AND @fecha_fin AND f.estado = 'COBRADA'
            GROUP BY CAST(f.fecha_hora AS DATE)
            ORDER BY Fecha ASC;
        END
        ELSE IF @agrupar_por = 'PRODUCTO'
        BEGIN
            SELECT
                p.nombre_producto,
                SUM(dp.cantidad) AS Unidades_Vendidas,
                SUM(dp.cantidad * dp.precio_unitario) AS Total_Recaudado,
                RANK() OVER (ORDER BY SUM(dp.cantidad * dp.precio_unitario) DESC) AS Ranking
            FROM dbo.DETALLE_PEDIDO dp
            INNER JOIN dbo.PRODUCTO p ON dp.id_producto = p.id_producto
            INNER JOIN dbo.PEDIDO ped ON dp.id_pedido = ped.id_pedido
            INNER JOIN dbo.FACTURA f ON ped.id_pedido = f.id_pedido
            WHERE f.fecha_hora BETWEEN @fecha_inicio AND @fecha_fin AND f.estado = 'COBRADA'
            GROUP BY p.nombre_producto;
        END
        ELSE IF @agrupar_por = 'MESERO'
        BEGIN
            SELECT
                e.nombre + ' ' + e.apellido AS Mesero,
                COUNT(DISTINCT p.id_pedido) AS Pedidos_Atendidos,
                SUM(f.total) AS Total_Vendido
            FROM dbo.FACTURA f
            INNER JOIN dbo.PEDIDO p ON f.id_pedido = p.id_pedido
            INNER JOIN dbo.EMPLEADO e ON p.id_empleado = e.id_empleado
            WHERE f.fecha_hora BETWEEN @fecha_inicio AND @fecha_fin AND f.estado = 'COBRADA'
            GROUP BY e.nombre, e.apellido
            ORDER BY Total_Vendido DESC;
        END
        ELSE
        BEGIN
            SELECT
                mp.nombre_metodo,
                COUNT(pg.id_pago) AS Transacciones,
                SUM(pg.monto) AS Total_Recaudado
            FROM dbo.PAGO pg
            INNER JOIN dbo.METODO_PAGO mp ON pg.id_metodo_pago = mp.id_metodo_pago
            INNER JOIN dbo.FACTURA f ON pg.id_factura = f.id_factura
            WHERE f.fecha_hora BETWEEN @fecha_inicio AND @fecha_fin AND f.estado = 'COBRADA'
            GROUP BY mp.nombre_metodo;
        END
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

/* ----------------------------------------------------------------------------
   sp_respaldar_bd
   BACKUP DATABASE no puede ejecutarse dentro de una transacción de usuario,
   por eso este procedimiento (y el siguiente) solo usan TRY/CATCH. El
   resultado, bueno o malo, siempre queda en RESPALDO_BD.
   La carpeta @ruta debe existir EN EL SERVIDOR de SQL Server.
   -------------------------------------------------------------------------- */
CREATE OR ALTER PROCEDURE dbo.sp_respaldar_bd
    @ruta NVARCHAR(260),
    @id_empleado INT = NULL,
    @id_respaldo INT OUTPUT,
    @archivo NVARCHAR(460) OUTPUT
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @bd SYSNAME = DB_NAME();
    DECLARE @nombre NVARCHAR(200);
    DECLARE @mensaje VARCHAR(500);

    BEGIN TRY
        IF @ruta IS NULL OR LEN(LTRIM(RTRIM(@ruta))) = 0
            THROW 50030, 'Debe indicar la carpeta de respaldos.', 1;

        SET @ruta = LTRIM(RTRIM(@ruta));

        IF RIGHT(@ruta, 1) NOT IN ('\', '/')
            SET @ruta = @ruta + N'\';

        SET @nombre = @bd + N'_' + FORMAT(SYSDATETIME(), 'yyyyMMdd_HHmmss_fff') + N'.bak';
        SET @archivo = @ruta + @nombre;

        BACKUP DATABASE @bd
        TO DISK = @archivo
        WITH NOINIT, CHECKSUM, STOP_ON_ERROR, NAME = @nombre;

        RESTORE VERIFYONLY FROM DISK = @archivo WITH CHECKSUM;

        INSERT INTO dbo.RESPALDO_BD (nombre_archivo, ruta_completa, id_empleado, verificado, resultado)
        VALUES (@nombre, @archivo, @id_empleado, 1, 'Respaldo generado y verificado correctamente.');

        SET @id_respaldo = SCOPE_IDENTITY();
    END TRY
    BEGIN CATCH
        SET @mensaje = LEFT(ERROR_MESSAGE(), 500);

        IF @nombre IS NOT NULL
            INSERT INTO dbo.RESPALDO_BD (nombre_archivo, ruta_completa, id_empleado, verificado, resultado)
            VALUES (@nombre, @archivo, @id_empleado, 0, @mensaje);

        THROW;
    END CATCH
END;
GO

/* ----------------------------------------------------------------------------
   sp_verificar_respaldo
   -------------------------------------------------------------------------- */
CREATE OR ALTER PROCEDURE dbo.sp_verificar_respaldo
    @id_respaldo INT
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @archivo NVARCHAR(460);
    DECLARE @mensaje VARCHAR(500);

    BEGIN TRY
        SELECT @archivo = ruta_completa
        FROM dbo.RESPALDO_BD
        WHERE id_respaldo = @id_respaldo;

        IF @archivo IS NULL
            THROW 50031, 'El respaldo indicado no existe en el historial.', 1;

        RESTORE VERIFYONLY FROM DISK = @archivo WITH CHECKSUM;

        UPDATE dbo.RESPALDO_BD
        SET verificado = 1,
            resultado = 'Verificación de integridad correcta.'
        WHERE id_respaldo = @id_respaldo;
    END TRY
    BEGIN CATCH
        SET @mensaje = LEFT(ERROR_MESSAGE(), 500);

        IF @archivo IS NOT NULL
            UPDATE dbo.RESPALDO_BD
            SET verificado = 0,
                resultado = @mensaje
            WHERE id_respaldo = @id_respaldo;

        THROW;
    END CATCH
END;
GO

/* ============================================================================
   8. ROLES, USUARIOS Y PERMISOS
   Un rol de base de datos por cada rol del restaurante, con lo mínimo que
   necesita. La aplicación se conecta con una cuenta propia y, si en .env se
   activa DB_IMPERSONAR_ROLES=true, cada petición de mesero/cocinero/cajero se
   ejecuta como el usuario app_<rol> (EXECUTE AS USER ... REVERT), de modo que
   SQL Server mismo rechaza lo que el rol no puede hacer.
   ========================================================================== */
PRINT N'8/8 Roles, usuarios y permisos';
GO

IF DATABASE_PRINCIPAL_ID(N'rol_administrador') IS NULL CREATE ROLE rol_administrador;
IF DATABASE_PRINCIPAL_ID(N'rol_mesero') IS NULL CREATE ROLE rol_mesero;
IF DATABASE_PRINCIPAL_ID(N'rol_cocinero') IS NULL CREATE ROLE rol_cocinero;
IF DATABASE_PRINCIPAL_ID(N'rol_cajero') IS NULL CREATE ROLE rol_cajero;
GO

-- Administrador: todo el esquema (tablas, vistas y procedimientos) y respaldos
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON SCHEMA::dbo TO rol_administrador;
GRANT BACKUP DATABASE TO rol_administrador;

-- Mesero: ve mesas y menú, toma pedidos y los entrega
GRANT SELECT ON dbo.MESA TO rol_mesero;
GRANT SELECT ON dbo.PRODUCTO TO rol_mesero;
GRANT SELECT ON dbo.CATEGORIA TO rol_mesero;
GRANT SELECT ON dbo.PEDIDO TO rol_mesero;
GRANT SELECT ON dbo.DETALLE_PEDIDO TO rol_mesero;
GRANT SELECT ON dbo.vw_menu_activo TO rol_mesero;
GRANT SELECT ON dbo.vw_mesas_estado TO rol_mesero;
GRANT UPDATE (estado) ON dbo.PEDIDO TO rol_mesero;
GRANT UPDATE (estado_cocina, fecha_actualizacion) ON dbo.DETALLE_PEDIDO TO rol_mesero;
GRANT EXECUTE ON dbo.sp_registrar_pedido TO rol_mesero;
GRANT EXECUTE ON dbo.sp_agregar_items_pedido TO rol_mesero;

-- Cocinero: ve la cola y cambia estados de cocina
GRANT SELECT ON dbo.PEDIDO TO rol_cocinero;
GRANT SELECT ON dbo.DETALLE_PEDIDO TO rol_cocinero;
GRANT SELECT ON dbo.PRODUCTO TO rol_cocinero;
GRANT SELECT ON dbo.MESA TO rol_cocinero;
GRANT SELECT ON dbo.vw_cola_cocina TO rol_cocinero;
GRANT UPDATE (estado) ON dbo.PEDIDO TO rol_cocinero;
GRANT UPDATE (estado_cocina, fecha_actualizacion) ON dbo.DETALLE_PEDIDO TO rol_cocinero;

-- Cajero: cobra, anula pedidos, cierra su turno. Nunca ve cédulas ni contraseñas.
GRANT SELECT ON dbo.PEDIDO TO rol_cajero;
GRANT SELECT ON dbo.DETALLE_PEDIDO TO rol_cajero;
GRANT SELECT ON dbo.PRODUCTO TO rol_cajero;
GRANT SELECT ON dbo.MESA TO rol_cajero;
GRANT SELECT ON dbo.METODO_PAGO TO rol_cajero;
GRANT SELECT ON dbo.FACTURA TO rol_cajero;
GRANT SELECT ON dbo.PAGO TO rol_cajero;
GRANT SELECT ON dbo.TURNO_CAJA TO rol_cajero;
GRANT SELECT ON dbo.CAJERO TO rol_cajero;
GRANT SELECT (id_cliente, nombre, apellido) ON dbo.CLIENTE TO rol_cajero;
GRANT SELECT (id_empleado, nombre, apellido) ON dbo.EMPLEADO TO rol_cajero;
GRANT SELECT ON dbo.vw_mesas_estado TO rol_cajero;
GRANT SELECT ON dbo.vw_factura_ticket TO rol_cajero;
GRANT UPDATE (fecha_cierre, monto_final) ON dbo.TURNO_CAJA TO rol_cajero;
GRANT EXECUTE ON dbo.sp_cobrar_cuenta TO rol_cajero;
GRANT EXECUTE ON dbo.sp_anular_pedido TO rol_cajero;

-- La bitácora de pedidos no la puede alterar nadie, ni el administrador
DENY UPDATE, DELETE ON dbo.AUDITORIA_PEDIDO TO rol_administrador;
DENY UPDATE, DELETE ON dbo.AUDITORIA_PEDIDO TO rol_mesero;
DENY UPDATE, DELETE ON dbo.AUDITORIA_PEDIDO TO rol_cocinero;
DENY UPDATE, DELETE ON dbo.AUDITORIA_PEDIDO TO rol_cajero;
GO

-- Usuarios sin inicio de sesión propio: la aplicación los "pone" con EXECUTE AS USER
IF DATABASE_PRINCIPAL_ID(N'app_administrador') IS NULL CREATE USER app_administrador WITHOUT LOGIN;
IF DATABASE_PRINCIPAL_ID(N'app_mesero') IS NULL CREATE USER app_mesero WITHOUT LOGIN;
IF DATABASE_PRINCIPAL_ID(N'app_cocinero') IS NULL CREATE USER app_cocinero WITHOUT LOGIN;
IF DATABASE_PRINCIPAL_ID(N'app_cajero') IS NULL CREATE USER app_cajero WITHOUT LOGIN;
GO

IF IS_ROLEMEMBER(N'rol_administrador', N'app_administrador') = 0 ALTER ROLE rol_administrador ADD MEMBER app_administrador;
IF IS_ROLEMEMBER(N'rol_mesero', N'app_mesero') = 0 ALTER ROLE rol_mesero ADD MEMBER app_mesero;
IF IS_ROLEMEMBER(N'rol_cocinero', N'app_cocinero') = 0 ALTER ROLE rol_cocinero ADD MEMBER app_cocinero;
IF IS_ROLEMEMBER(N'rol_cajero', N'app_cajero') = 0 ALTER ROLE rol_cajero ADD MEMBER app_cajero;
GO

PRINT N'Listo. Si la base está vacía, ejecute ahora datos_iniciales.sql.';
GO
