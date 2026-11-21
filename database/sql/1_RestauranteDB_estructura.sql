USE master
GO

IF DB_ID(N'RestauranteDB') IS NULL
    CREATE DATABASE RestauranteDB
GO

ALTER DATABASE RestauranteDB SET ALLOW_SNAPSHOT_ISOLATION ON
GO

IF EXISTS (SELECT 1 FROM sys.databases WHERE name = N'RestauranteDB' AND is_read_committed_snapshot_on = 0)
    ALTER DATABASE RestauranteDB SET READ_COMMITTED_SNAPSHOT ON WITH ROLLBACK IMMEDIATE
GO

USE RestauranteDB
GO

SET QUOTED_IDENTIFIER ON
SET ANSI_NULLS ON
GO

IF OBJECT_ID(N'dbo.ROL', N'U') IS NULL
CREATE TABLE dbo.ROL (
    id_rol      INT IDENTITY(1,1) CONSTRAINT PK_ROL PRIMARY KEY,
    nombre_rol  VARCHAR(50) NOT NULL CONSTRAINT UQ_ROL_nombre_rol UNIQUE
)

IF OBJECT_ID(N'dbo.PERMISO', N'U') IS NULL
CREATE TABLE dbo.PERMISO (
    id_permiso   INT IDENTITY(1,1) CONSTRAINT PK_PERMISO PRIMARY KEY,
    clave        VARCHAR(60) NOT NULL CONSTRAINT UQ_PERMISO_clave UNIQUE,
    modulo       VARCHAR(50) NOT NULL,
    descripcion  VARCHAR(150) NOT NULL
)

IF OBJECT_ID(N'dbo.ROL_PERMISO', N'U') IS NULL
CREATE TABLE dbo.ROL_PERMISO (
    id_rol      INT NOT NULL CONSTRAINT FK_ROL_PERMISO_id_rol REFERENCES dbo.ROL(id_rol) ON DELETE CASCADE,
    id_permiso  INT NOT NULL CONSTRAINT FK_ROL_PERMISO_id_permiso REFERENCES dbo.PERMISO(id_permiso) ON DELETE CASCADE,
    CONSTRAINT PK_ROL_PERMISO PRIMARY KEY (id_rol, id_permiso)
)

IF OBJECT_ID(N'dbo.ESTACION', N'U') IS NULL
CREATE TABLE dbo.ESTACION (
    id_estacion      INT IDENTITY(1,1) CONSTRAINT PK_ESTACION PRIMARY KEY,
    nombre_estacion  VARCHAR(50) NOT NULL CONSTRAINT UQ_ESTACION_nombre_estacion UNIQUE
)

IF OBJECT_ID(N'dbo.CATEGORIA', N'U') IS NULL
CREATE TABLE dbo.CATEGORIA (
    id_categoria      INT IDENTITY(1,1) CONSTRAINT PK_CATEGORIA PRIMARY KEY,
    nombre_categoria  VARCHAR(50) NOT NULL CONSTRAINT UQ_CATEGORIA_nombre_categoria UNIQUE,
    descripcion       VARCHAR(200) NULL
)

IF OBJECT_ID(N'dbo.METODO_PAGO', N'U') IS NULL
CREATE TABLE dbo.METODO_PAGO (
    id_metodo_pago  INT IDENTITY(1,1) CONSTRAINT PK_METODO_PAGO PRIMARY KEY,
    nombre_metodo   VARCHAR(50) NOT NULL CONSTRAINT UQ_METODO_PAGO_nombre_metodo UNIQUE
)

IF OBJECT_ID(N'dbo.EMPLEADO', N'U') IS NULL
CREATE TABLE dbo.EMPLEADO (
    id_empleado      INT IDENTITY(1,1) CONSTRAINT PK_EMPLEADO PRIMARY KEY,
    nombre           VARCHAR(50) NOT NULL,
    apellido         VARCHAR(50) NOT NULL,
    correo           VARCHAR(100) NOT NULL CONSTRAINT UQ_EMPLEADO_correo UNIQUE,
    contrasena_hash  VARCHAR(255) NOT NULL,
    id_rol           INT NOT NULL CONSTRAINT FK_EMPLEADO_id_rol REFERENCES dbo.ROL(id_rol),
    fecha_ingreso    DATETIME NOT NULL CONSTRAINT DF_EMPLEADO_fecha_ingreso DEFAULT GETDATE(),
    activo           BIT NOT NULL CONSTRAINT DF_EMPLEADO_activo DEFAULT 1
)

IF OBJECT_ID(N'dbo.MESERO', N'U') IS NULL
CREATE TABLE dbo.MESERO (
    id_empleado    INT CONSTRAINT PK_MESERO PRIMARY KEY CONSTRAINT FK_MESERO_id_empleado REFERENCES dbo.EMPLEADO(id_empleado) ON DELETE CASCADE,
    zona_asignada  VARCHAR(50) NOT NULL
)

IF OBJECT_ID(N'dbo.COCINERO', N'U') IS NULL
CREATE TABLE dbo.COCINERO (
    id_empleado   INT CONSTRAINT PK_COCINERO PRIMARY KEY CONSTRAINT FK_COCINERO_id_empleado REFERENCES dbo.EMPLEADO(id_empleado) ON DELETE CASCADE,
    id_estacion   INT NOT NULL CONSTRAINT FK_COCINERO_id_estacion REFERENCES dbo.ESTACION(id_estacion),
    especialidad  VARCHAR(50) NOT NULL
)

IF OBJECT_ID(N'dbo.CAJERO', N'U') IS NULL
CREATE TABLE dbo.CAJERO (
    id_empleado  INT CONSTRAINT PK_CAJERO PRIMARY KEY CONSTRAINT FK_CAJERO_id_empleado REFERENCES dbo.EMPLEADO(id_empleado) ON DELETE CASCADE,
    codigo_caja  VARCHAR(20) NOT NULL CONSTRAINT UQ_CAJERO_codigo_caja UNIQUE
)

IF OBJECT_ID(N'dbo.MESA', N'U') IS NULL
CREATE TABLE dbo.MESA (
    id_mesa      INT IDENTITY(1,1) CONSTRAINT PK_MESA PRIMARY KEY,
    numero_mesa  INT NOT NULL CONSTRAINT UQ_MESA_numero_mesa UNIQUE,
    capacidad    INT NOT NULL CONSTRAINT CK_MESA_capacidad CHECK (capacidad > 0),
    zona         VARCHAR(50) NOT NULL,
    estado       VARCHAR(20) NOT NULL CONSTRAINT DF_MESA_estado DEFAULT 'DISPONIBLE'
                 CONSTRAINT CK_MESA_estado CHECK (estado IN ('DISPONIBLE', 'OCUPADA', 'RESERVADA', 'MANTENIMIENTO'))
)

IF OBJECT_ID(N'dbo.CLIENTE', N'U') IS NULL
CREATE TABLE dbo.CLIENTE (
    id_cliente  INT IDENTITY(1,1) CONSTRAINT PK_CLIENTE PRIMARY KEY,
    cedula      VARCHAR(20) NOT NULL CONSTRAINT UQ_CLIENTE_cedula UNIQUE,
    nombre      VARCHAR(50) NOT NULL,
    apellido    VARCHAR(50) NOT NULL,
    telefono    VARCHAR(20) NULL,
    correo      VARCHAR(100) NULL
)

IF OBJECT_ID(N'dbo.PRODUCTO', N'U') IS NULL
CREATE TABLE dbo.PRODUCTO (
    id_producto      INT IDENTITY(1,1) CONSTRAINT PK_PRODUCTO PRIMARY KEY,
    nombre_producto  VARCHAR(100) NOT NULL CONSTRAINT UQ_PRODUCTO_nombre_producto UNIQUE,
    descripcion      VARCHAR(255) NULL,
    precio           DECIMAL(10,2) NOT NULL CONSTRAINT CK_PRODUCTO_precio CHECK (precio >= 0),
    id_categoria     INT NOT NULL CONSTRAINT FK_PRODUCTO_id_categoria REFERENCES dbo.CATEGORIA(id_categoria),
    id_estacion      INT NOT NULL CONSTRAINT FK_PRODUCTO_id_estacion REFERENCES dbo.ESTACION(id_estacion),
    activo           BIT NOT NULL CONSTRAINT DF_PRODUCTO_activo DEFAULT 1,
    foto             VARCHAR(255) NULL
)

IF OBJECT_ID(N'dbo.INSUMO', N'U') IS NULL
CREATE TABLE dbo.INSUMO (
    id_insumo       INT IDENTITY(1,1) CONSTRAINT PK_INSUMO PRIMARY KEY,
    nombre_insumo   VARCHAR(100) NOT NULL CONSTRAINT UQ_INSUMO_nombre_insumo UNIQUE,
    unidad_medida   VARCHAR(20) NOT NULL,
    stock_actual    DECIMAL(10,2) NOT NULL CONSTRAINT CK_INSUMO_stock_actual CHECK (stock_actual >= 0),
    stock_minimo    DECIMAL(10,2) NOT NULL CONSTRAINT CK_INSUMO_stock_minimo CHECK (stock_minimo >= 0),
    costo_unitario  DECIMAL(10,2) NOT NULL CONSTRAINT CK_INSUMO_costo_unitario CHECK (costo_unitario >= 0)
)

IF OBJECT_ID(N'dbo.RECETA', N'U') IS NULL
CREATE TABLE dbo.RECETA (
    id_producto  INT NOT NULL CONSTRAINT FK_RECETA_id_producto REFERENCES dbo.PRODUCTO(id_producto) ON DELETE CASCADE,
    id_insumo    INT NOT NULL CONSTRAINT FK_RECETA_id_insumo REFERENCES dbo.INSUMO(id_insumo),
    cantidad     DECIMAL(10,2) NOT NULL CONSTRAINT CK_RECETA_cantidad CHECK (cantidad > 0),
    CONSTRAINT PK_RECETA PRIMARY KEY (id_producto, id_insumo)
)

IF OBJECT_ID(N'dbo.PEDIDO', N'U') IS NULL
CREATE TABLE dbo.PEDIDO (
    id_pedido           INT IDENTITY(1,1) CONSTRAINT PK_PEDIDO PRIMARY KEY,
    token_idempotencia  VARCHAR(100) NOT NULL CONSTRAINT UQ_PEDIDO_token_idempotencia UNIQUE,
    id_mesa             INT NOT NULL CONSTRAINT FK_PEDIDO_id_mesa REFERENCES dbo.MESA(id_mesa),
    id_empleado         INT NOT NULL CONSTRAINT FK_PEDIDO_id_empleado REFERENCES dbo.EMPLEADO(id_empleado),
    fecha_hora          DATETIME NOT NULL CONSTRAINT DF_PEDIDO_fecha_hora DEFAULT GETDATE(),
    estado              VARCHAR(20) NOT NULL CONSTRAINT DF_PEDIDO_estado DEFAULT 'ABIERTO'
                        CONSTRAINT CK_PEDIDO_estado CHECK (estado IN ('ABIERTO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO', 'FACTURADO', 'ANULADO')),
    subtotal            DECIMAL(10,2) NOT NULL CONSTRAINT DF_PEDIDO_subtotal DEFAULT 0 CONSTRAINT CK_PEDIDO_subtotal CHECK (subtotal >= 0),
    observaciones       VARCHAR(255) NULL,
    anulado_por         INT NULL CONSTRAINT FK_PEDIDO_anulado_por REFERENCES dbo.EMPLEADO(id_empleado),
    motivo_anulacion    VARCHAR(255) NULL,
    CONSTRAINT CK_PEDIDO_Anulacion CHECK (estado <> 'ANULADO' OR (anulado_por IS NOT NULL AND motivo_anulacion IS NOT NULL))
)

IF OBJECT_ID(N'dbo.DETALLE_PEDIDO', N'U') IS NULL
CREATE TABLE dbo.DETALLE_PEDIDO (
    id_pedido            INT NOT NULL CONSTRAINT FK_DETALLE_PEDIDO_id_pedido REFERENCES dbo.PEDIDO(id_pedido) ON DELETE CASCADE,
    num_linea            INT NOT NULL,
    id_producto          INT NOT NULL CONSTRAINT FK_DETALLE_PEDIDO_id_producto REFERENCES dbo.PRODUCTO(id_producto),
    cantidad             INT NOT NULL CONSTRAINT CK_DETALLE_PEDIDO_cantidad CHECK (cantidad > 0),
    precio_unitario      DECIMAL(10,2) NOT NULL CONSTRAINT CK_DETALLE_PEDIDO_precio_unitario CHECK (precio_unitario >= 0),
    estado_cocina        VARCHAR(20) NOT NULL CONSTRAINT DF_DETALLE_PEDIDO_estado_cocina DEFAULT 'PENDIENTE'
                         CONSTRAINT CK_DETALLE_PEDIDO_estado_cocina CHECK (estado_cocina IN ('PENDIENTE', 'PREPARANDO', 'LISTO', 'ENTREGADO', 'CANCELADO')),
    notas                VARCHAR(200) NULL,
    fecha_actualizacion  DATETIME NOT NULL CONSTRAINT DF_DETALLE_PEDIDO_fecha_actualizacion DEFAULT GETDATE(),
    CONSTRAINT PK_DETALLE_PEDIDO PRIMARY KEY (id_pedido, num_linea)
)

IF OBJECT_ID(N'dbo.TURNO_CAJA', N'U') IS NULL
CREATE TABLE dbo.TURNO_CAJA (
    id_turno        INT IDENTITY(1,1) CONSTRAINT PK_TURNO_CAJA PRIMARY KEY,
    id_empleado     INT NOT NULL CONSTRAINT FK_TURNO_CAJA_id_empleado REFERENCES dbo.EMPLEADO(id_empleado),
    fecha_apertura  DATETIME NOT NULL CONSTRAINT DF_TURNO_CAJA_fecha_apertura DEFAULT GETDATE(),
    fecha_cierre    DATETIME NULL,
    monto_inicial   DECIMAL(10,2) NOT NULL CONSTRAINT CK_TURNO_CAJA_monto_inicial CHECK (monto_inicial >= 0),
    monto_final     DECIMAL(10,2) NULL CONSTRAINT CK_TURNO_CAJA_monto_final CHECK (monto_final >= 0),
    CONSTRAINT CK_TURNO_CAJA_Cierre CHECK (fecha_cierre IS NULL OR (fecha_cierre >= fecha_apertura AND monto_final IS NOT NULL))
)

IF OBJECT_ID(N'dbo.FACTURA', N'U') IS NULL
CREATE TABLE dbo.FACTURA (
    id_factura        INT IDENTITY(1,1) CONSTRAINT PK_FACTURA PRIMARY KEY,
    numero_factura    VARCHAR(50) NOT NULL CONSTRAINT UQ_FACTURA_numero_factura UNIQUE,
    id_pedido         INT NOT NULL CONSTRAINT UQ_FACTURA_id_pedido UNIQUE CONSTRAINT FK_FACTURA_id_pedido REFERENCES dbo.PEDIDO(id_pedido),
    id_cliente        INT NULL CONSTRAINT FK_FACTURA_id_cliente REFERENCES dbo.CLIENTE(id_cliente),
    id_empleado       INT NOT NULL CONSTRAINT FK_FACTURA_id_empleado REFERENCES dbo.EMPLEADO(id_empleado),
    id_turno          INT NOT NULL CONSTRAINT FK_FACTURA_id_turno REFERENCES dbo.TURNO_CAJA(id_turno),
    fecha_hora        DATETIME NOT NULL CONSTRAINT DF_FACTURA_fecha_hora DEFAULT GETDATE(),
    subtotal          DECIMAL(10,2) NOT NULL CONSTRAINT CK_FACTURA_subtotal CHECK (subtotal >= 0),
    descuento         DECIMAL(10,2) NOT NULL CONSTRAINT DF_FACTURA_descuento DEFAULT 0 CONSTRAINT CK_FACTURA_descuento CHECK (descuento >= 0),
    impuesto_ventas   DECIMAL(10,2) NOT NULL CONSTRAINT CK_FACTURA_impuesto_ventas CHECK (impuesto_ventas >= 0),
    servicio          DECIMAL(10,2) NOT NULL CONSTRAINT CK_FACTURA_servicio CHECK (servicio >= 0),
    total             DECIMAL(10,2) NOT NULL CONSTRAINT CK_FACTURA_total CHECK (total >= 0),
    vuelto            DECIMAL(10,2) NOT NULL CONSTRAINT DF_FACTURA_vuelto DEFAULT 0 CONSTRAINT CK_FACTURA_vuelto CHECK (vuelto >= 0),
    estado            VARCHAR(20) NOT NULL CONSTRAINT DF_FACTURA_estado DEFAULT 'COBRADA' CONSTRAINT CK_FACTURA_estado CHECK (estado IN ('COBRADA', 'ANULADA')),
    anulada_por       INT NULL CONSTRAINT FK_FACTURA_anulada_por REFERENCES dbo.EMPLEADO(id_empleado),
    motivo_anulacion  VARCHAR(255) NULL,
    fecha_anulacion   DATETIME NULL,
    CONSTRAINT CK_FACTURA_CalculoTotal CHECK (descuento <= subtotal AND total = subtotal - descuento + impuesto_ventas + servicio),
    CONSTRAINT CK_FACTURA_Anulacion CHECK (estado <> 'ANULADA' OR (anulada_por IS NOT NULL AND motivo_anulacion IS NOT NULL AND fecha_anulacion IS NOT NULL))
)

IF OBJECT_ID(N'dbo.PAGO', N'U') IS NULL
CREATE TABLE dbo.PAGO (
    id_pago         INT IDENTITY(1,1) CONSTRAINT PK_PAGO PRIMARY KEY,
    id_factura      INT NOT NULL CONSTRAINT FK_PAGO_id_factura REFERENCES dbo.FACTURA(id_factura),
    id_metodo_pago  INT NOT NULL CONSTRAINT FK_PAGO_id_metodo_pago REFERENCES dbo.METODO_PAGO(id_metodo_pago),
    monto           DECIMAL(10,2) NOT NULL CONSTRAINT CK_PAGO_monto CHECK (monto > 0),
    referencia      VARCHAR(100) NULL,
    fecha_hora      DATETIME NOT NULL CONSTRAINT DF_PAGO_fecha_hora DEFAULT GETDATE()
)

IF OBJECT_ID(N'dbo.AUDITORIA_PEDIDO', N'U') IS NULL
CREATE TABLE dbo.AUDITORIA_PEDIDO (
    id_auditoria       INT IDENTITY(1,1) CONSTRAINT PK_AUDITORIA_PEDIDO PRIMARY KEY,
    id_pedido          INT NOT NULL,
    accion             VARCHAR(20) NOT NULL,
    usuario_bd         VARCHAR(100) NOT NULL CONSTRAINT DF_AUDITORIA_PEDIDO_usuario_bd DEFAULT SUSER_SNAME(),
    id_empleado_app    INT NULL,
    fecha_hora         DATETIME NOT NULL CONSTRAINT DF_AUDITORIA_PEDIDO_fecha_hora DEFAULT GETDATE(),
    estado_anterior    VARCHAR(20) NULL,
    estado_nuevo       VARCHAR(20) NULL,
    subtotal_anterior  DECIMAL(10,2) NULL,
    subtotal_nuevo     DECIMAL(10,2) NULL
)

IF OBJECT_ID(N'dbo.ALERTA_STOCK', N'U') IS NULL
CREATE TABLE dbo.ALERTA_STOCK (
    id_alerta     INT IDENTITY(1,1) CONSTRAINT PK_ALERTA_STOCK PRIMARY KEY,
    id_insumo     INT NOT NULL CONSTRAINT FK_ALERTA_STOCK_id_insumo REFERENCES dbo.INSUMO(id_insumo),
    stock_actual  DECIMAL(10,2) NOT NULL,
    stock_minimo  DECIMAL(10,2) NOT NULL,
    fecha_hora    DATETIME NOT NULL CONSTRAINT DF_ALERTA_STOCK_fecha_hora DEFAULT GETDATE(),
    atendida      BIT NOT NULL CONSTRAINT DF_ALERTA_STOCK_atendida DEFAULT 0
)

IF OBJECT_ID(N'dbo.RESPALDO_BD', N'U') IS NULL
CREATE TABLE dbo.RESPALDO_BD (
    id_respaldo     INT IDENTITY(1,1) CONSTRAINT PK_RESPALDO_BD PRIMARY KEY,
    nombre_archivo  NVARCHAR(200) NOT NULL,
    ruta_completa   NVARCHAR(460) NOT NULL,
    fecha_hora      DATETIME NOT NULL CONSTRAINT DF_RESPALDO_BD_fecha_hora DEFAULT GETDATE(),
    id_empleado     INT NULL CONSTRAINT FK_RESPALDO_BD_id_empleado REFERENCES dbo.EMPLEADO(id_empleado),
    verificado      BIT NOT NULL CONSTRAINT DF_RESPALDO_BD_verificado DEFAULT 0,
    resultado       VARCHAR(500) NULL
)

IF OBJECT_ID(N'dbo.SESION_ACTIVA', N'U') IS NULL
CREATE TABLE dbo.SESION_ACTIVA (
    id_sesion         INT IDENTITY(1,1) CONSTRAINT PK_SESION_ACTIVA PRIMARY KEY,
    id_empleado       INT NOT NULL CONSTRAINT UQ_SESION_ACTIVA_id_empleado UNIQUE
                      CONSTRAINT FK_SESION_ACTIVA_id_empleado REFERENCES dbo.EMPLEADO(id_empleado) ON DELETE CASCADE,
    token             CHAR(64) NOT NULL,
    direccion_ip      VARCHAR(45) NULL,
    navegador         VARCHAR(255) NULL,
    fecha_inicio      DATETIME NOT NULL CONSTRAINT DF_SESION_ACTIVA_fecha_inicio DEFAULT GETDATE(),
    ultima_actividad  DATETIME NOT NULL CONSTRAINT DF_SESION_ACTIVA_ultima_actividad DEFAULT GETDATE()
)
GO

IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = N'CK_FACTURA_Total' AND parent_object_id = OBJECT_ID(N'dbo.FACTURA') AND parent_column_id = 0)
    EXEC sp_rename N'dbo.CK_FACTURA_Total', N'CK_FACTURA_CalculoTotal', N'OBJECT'
GO

DECLARE @renombrar NVARCHAR(MAX) = N''

SELECT @renombrar += N'EXEC sp_rename N''dbo.' + nombre_actual + N''', N''' + nombre_nuevo + N''', N''OBJECT''' + CHAR(10)
FROM (
    SELECT k.name AS nombre_actual,
           CONCAT(k.type, '_', t.name, CASE WHEN k.type = 'UQ' THEN '_' + COL_NAME(ic.object_id, ic.column_id) END) AS nombre_nuevo
    FROM sys.key_constraints k
    INNER JOIN sys.tables t ON t.object_id = k.parent_object_id
    INNER JOIN sys.index_columns ic ON ic.object_id = k.parent_object_id AND ic.index_id = k.unique_index_id AND ic.key_ordinal = 1
    WHERE k.is_system_named = 1
    UNION ALL
    SELECT f.name, CONCAT('FK_', t.name, '_', COL_NAME(fc.parent_object_id, fc.parent_column_id))
    FROM sys.foreign_keys f
    INNER JOIN sys.tables t ON t.object_id = f.parent_object_id
    INNER JOIN sys.foreign_key_columns fc ON fc.constraint_object_id = f.object_id
    WHERE f.is_system_named = 1
    UNION ALL
    SELECT c.name, CONCAT('CK_', t.name, '_', COL_NAME(c.parent_object_id, c.parent_column_id))
    FROM sys.check_constraints c
    INNER JOIN sys.tables t ON t.object_id = c.parent_object_id
    WHERE c.is_system_named = 1 AND c.parent_column_id > 0
    UNION ALL
    SELECT d.name, CONCAT('DF_', t.name, '_', COL_NAME(d.parent_object_id, d.parent_column_id))
    FROM sys.default_constraints d
    INNER JOIN sys.tables t ON t.object_id = d.parent_object_id
    WHERE d.is_system_named = 1
) restricciones
WHERE OBJECT_ID(N'dbo.' + nombre_nuevo) IS NULL

EXEC sys.sp_executesql @renombrar
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_DETALLE_PEDIDO_Cocina')
    CREATE INDEX IX_DETALLE_PEDIDO_Cocina ON dbo.DETALLE_PEDIDO (estado_cocina, id_pedido)

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_DETALLE_PEDIDO_Producto')
    CREATE INDEX IX_DETALLE_PEDIDO_Producto ON dbo.DETALLE_PEDIDO (id_producto) INCLUDE (cantidad, precio_unitario, estado_cocina)

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_PEDIDO_MesaEstado')
    CREATE INDEX IX_PEDIDO_MesaEstado ON dbo.PEDIDO (id_mesa, estado)

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_PEDIDO_Estado_Fecha')
    CREATE INDEX IX_PEDIDO_Estado_Fecha ON dbo.PEDIDO (estado, fecha_hora) INCLUDE (id_mesa, subtotal)

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_FACTURA_FechaTotal')
    CREATE INDEX IX_FACTURA_FechaTotal ON dbo.FACTURA (fecha_hora) INCLUDE (estado, total)

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_FACTURA_Turno')
    CREATE INDEX IX_FACTURA_Turno ON dbo.FACTURA (id_turno) INCLUDE (estado, total, vuelto)

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_PAGO_Factura')
    CREATE INDEX IX_PAGO_Factura ON dbo.PAGO (id_factura) INCLUDE (id_metodo_pago, monto)

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_PRODUCTO_CategoriaActivo')
    CREATE INDEX IX_PRODUCTO_CategoriaActivo ON dbo.PRODUCTO (id_categoria, activo) INCLUDE (nombre_producto, precio)

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_ALERTA_STOCK_Pendiente')
    CREATE INDEX IX_ALERTA_STOCK_Pendiente ON dbo.ALERTA_STOCK (atendida, id_insumo)

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UQ_TURNO_CAJA_Abierto')
    CREATE UNIQUE INDEX UQ_TURNO_CAJA_Abierto ON dbo.TURNO_CAJA (id_empleado) WHERE fecha_cierre IS NULL

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UQ_PEDIDO_MesaAbierta')
    CREATE UNIQUE INDEX UQ_PEDIDO_MesaAbierta ON dbo.PEDIDO (id_mesa)
        WHERE estado IN ('ABIERTO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO')
GO

PRINT N'Estructura lista. Ahora ejecute 2_RestauranteDB_programacion.sql.'
GO
