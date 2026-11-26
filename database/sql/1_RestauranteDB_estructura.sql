CREATE DATABASE RestauranteDB;
GO

ALTER DATABASE RestauranteDB SET ALLOW_SNAPSHOT_ISOLATION ON;
GO

ALTER DATABASE RestauranteDB SET READ_COMMITTED_SNAPSHOT ON WITH ROLLBACK IMMEDIATE;
GO

USE RestauranteDB;
GO

SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO


-- =========================================
-- CIFRADO DE DATOS PERSONALES
-- Jerarquía de llaves de SQL Server:
--   llave maestra del servicio -> llave maestra de la base -> certificado -> llave simétrica AES-256.
-- La llave maestra de la base queda protegida también por la llave maestra del servicio,
-- así SQL Server la abre sola en este servidor; en otro servidor (por ejemplo, con un .bak
-- robado) hace falta la contraseña. En producción la contraseña se guarda fuera del script.
-- =========================================

CREATE MASTER KEY ENCRYPTION BY PASSWORD = 'Cuchara#Luison-LlaveMaestra-2026';
GO

CREATE CERTIFICATE cert_datos_personales
    WITH SUBJECT = 'Cifrado de datos personales de clientes';
GO

CREATE SYMMETRIC KEY llave_datos_personales
    WITH ALGORITHM = AES_256
    ENCRYPTION BY CERTIFICATE cert_datos_personales;
GO

-- =========================================
-- TABLA ROL
-- =========================================

CREATE TABLE ROL (
    id_rol INT IDENTITY(1,1) PRIMARY KEY,
    nombre_rol VARCHAR(50) UNIQUE NOT NULL
);
GO


-- =========================================
-- TABLA PERMISO
-- =========================================

CREATE TABLE PERMISO (
    id_permiso INT IDENTITY(1,1) PRIMARY KEY,
    clave VARCHAR(60) UNIQUE NOT NULL,
    modulo VARCHAR(50) NOT NULL,
    descripcion VARCHAR(150) NOT NULL
);
GO


-- =========================================
-- TABLA ROL_PERMISO
-- =========================================

CREATE TABLE ROL_PERMISO (
    id_rol INT NOT NULL,
    id_permiso INT NOT NULL,

    PRIMARY KEY (id_rol, id_permiso),

    CONSTRAINT FK_ROL_PERMISO_ROL
        FOREIGN KEY (id_rol)
        REFERENCES ROL(id_rol)
        ON DELETE CASCADE,

    CONSTRAINT FK_ROL_PERMISO_PERMISO
        FOREIGN KEY (id_permiso)
        REFERENCES PERMISO(id_permiso)
        ON DELETE CASCADE
);
GO


-- =========================================
-- TABLA ESTACION
-- =========================================

CREATE TABLE ESTACION (
    id_estacion INT IDENTITY(1,1) PRIMARY KEY,
    nombre_estacion VARCHAR(50) UNIQUE NOT NULL
);
GO


-- =========================================
-- TABLA CATEGORIA
-- =========================================

CREATE TABLE CATEGORIA (
    id_categoria INT IDENTITY(1,1) PRIMARY KEY,
    nombre_categoria VARCHAR(50) UNIQUE NOT NULL,
    descripcion VARCHAR(200)
);
GO


-- =========================================
-- TABLA METODO_PAGO
-- =========================================

CREATE TABLE METODO_PAGO (
    id_metodo_pago INT IDENTITY(1,1) PRIMARY KEY,
    nombre_metodo VARCHAR(50) UNIQUE NOT NULL
);
GO


-- =========================================
-- TABLA EMPLEADO
-- =========================================

CREATE TABLE EMPLEADO (
    id_empleado INT IDENTITY(1,1) PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    apellido VARCHAR(50) NOT NULL,
    correo VARCHAR(100) UNIQUE NOT NULL,
    contrasena_hash VARCHAR(255) NOT NULL,
    id_rol INT NOT NULL,
    fecha_ingreso DATETIME NOT NULL DEFAULT GETDATE(),
    activo BIT NOT NULL DEFAULT 1,

    CONSTRAINT FK_EMPLEADO_ROL
        FOREIGN KEY (id_rol)
        REFERENCES ROL(id_rol)
);
GO


-- =========================================
-- TABLA MESERO
-- =========================================

CREATE TABLE MESERO (
    id_empleado INT PRIMARY KEY,
    zona_asignada VARCHAR(50) NOT NULL,

    CONSTRAINT FK_MESERO_EMPLEADO
        FOREIGN KEY (id_empleado)
        REFERENCES EMPLEADO(id_empleado)
        ON DELETE CASCADE
);
GO


-- =========================================
-- TABLA COCINERO
-- =========================================

CREATE TABLE COCINERO (
    id_empleado INT PRIMARY KEY,
    id_estacion INT NOT NULL,
    especialidad VARCHAR(50) NOT NULL,

    CONSTRAINT FK_COCINERO_EMPLEADO
        FOREIGN KEY (id_empleado)
        REFERENCES EMPLEADO(id_empleado)
        ON DELETE CASCADE,

    CONSTRAINT FK_COCINERO_ESTACION
        FOREIGN KEY (id_estacion)
        REFERENCES ESTACION(id_estacion)
);
GO


-- =========================================
-- TABLA CAJERO
-- =========================================

CREATE TABLE CAJERO (
    id_empleado INT PRIMARY KEY,
    codigo_caja VARCHAR(20) UNIQUE NOT NULL,

    CONSTRAINT FK_CAJERO_EMPLEADO
        FOREIGN KEY (id_empleado)
        REFERENCES EMPLEADO(id_empleado)
        ON DELETE CASCADE
);
GO


-- =========================================
-- TABLA MESA
-- =========================================

CREATE TABLE MESA (
    id_mesa INT IDENTITY(1,1) PRIMARY KEY,
    numero_mesa INT UNIQUE NOT NULL,
    capacidad INT NOT NULL CHECK (capacidad > 0),
    zona VARCHAR(50) NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'DISPONIBLE'
        CHECK (estado IN ('DISPONIBLE', 'OCUPADA', 'RESERVADA', 'MANTENIMIENTO'))
);
GO


-- =========================================
-- TABLA CLIENTE
-- =========================================

CREATE TABLE CLIENTE (
    id_cliente INT IDENTITY(1,1) PRIMARY KEY,
    -- Cédula cifrada con AES-256 (ENCRYPTBYKEY). No se puede indexar ni comparar cifrada,
    -- por eso se guarda además su huella SHA2-256, que es la que garantiza que no se repita.
    cedula VARBINARY(256) NOT NULL,
    cedula_hash BINARY(32) UNIQUE NOT NULL,
    nombre VARCHAR(50) NOT NULL,
    apellido VARCHAR(50) NOT NULL,
    telefono VARCHAR(20),
    correo VARCHAR(100)
);
GO


-- =========================================
-- TABLA PRODUCTO
-- =========================================

CREATE TABLE PRODUCTO (
    id_producto INT IDENTITY(1,1) PRIMARY KEY,
    nombre_producto VARCHAR(100) UNIQUE NOT NULL,
    descripcion VARCHAR(255),
    precio DECIMAL(10,2) NOT NULL CHECK (precio >= 0),
    id_categoria INT NOT NULL,
    id_estacion INT NOT NULL,
    activo BIT NOT NULL DEFAULT 1,
    foto VARCHAR(255),

    CONSTRAINT FK_PRODUCTO_CATEGORIA
        FOREIGN KEY (id_categoria)
        REFERENCES CATEGORIA(id_categoria),

    CONSTRAINT FK_PRODUCTO_ESTACION
        FOREIGN KEY (id_estacion)
        REFERENCES ESTACION(id_estacion)
);
GO


-- =========================================
-- TABLA INSUMO
-- =========================================

CREATE TABLE INSUMO (
    id_insumo INT IDENTITY(1,1) PRIMARY KEY,
    nombre_insumo VARCHAR(100) UNIQUE NOT NULL,
    unidad_medida VARCHAR(20) NOT NULL,
    stock_actual DECIMAL(10,2) NOT NULL CHECK (stock_actual >= 0),
    stock_minimo DECIMAL(10,2) NOT NULL CHECK (stock_minimo >= 0),
    costo_unitario DECIMAL(10,2) NOT NULL CHECK (costo_unitario >= 0)
);
GO


-- =========================================
-- TABLA RECETA
-- =========================================

CREATE TABLE RECETA (
    id_producto INT NOT NULL,
    id_insumo INT NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL CHECK (cantidad > 0),

    PRIMARY KEY (id_producto, id_insumo),

    CONSTRAINT FK_RECETA_PRODUCTO
        FOREIGN KEY (id_producto)
        REFERENCES PRODUCTO(id_producto)
        ON DELETE CASCADE,

    CONSTRAINT FK_RECETA_INSUMO
        FOREIGN KEY (id_insumo)
        REFERENCES INSUMO(id_insumo)
);
GO


-- =========================================
-- TABLA PEDIDO
-- =========================================

CREATE TABLE PEDIDO (
    id_pedido INT IDENTITY(1,1) PRIMARY KEY,
    token_idempotencia VARCHAR(100) UNIQUE NOT NULL,
    id_mesa INT NOT NULL,
    id_empleado INT NOT NULL,
    fecha_hora DATETIME NOT NULL DEFAULT GETDATE(),
    estado VARCHAR(20) NOT NULL DEFAULT 'ABIERTO'
        CHECK (estado IN ('ABIERTO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO', 'FACTURADO', 'ANULADO')),
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0 CHECK (subtotal >= 0),
    observaciones VARCHAR(255),
    anulado_por INT,
    motivo_anulacion VARCHAR(255),

    CONSTRAINT CK_PEDIDO_Anulacion CHECK (estado <> 'ANULADO' OR (anulado_por IS NOT NULL AND motivo_anulacion IS NOT NULL)),

    CONSTRAINT FK_PEDIDO_MESA
        FOREIGN KEY (id_mesa)
        REFERENCES MESA(id_mesa),

    CONSTRAINT FK_PEDIDO_EMPLEADO
        FOREIGN KEY (id_empleado)
        REFERENCES EMPLEADO(id_empleado),

    CONSTRAINT FK_PEDIDO_EMPLEADO_ANULA
        FOREIGN KEY (anulado_por)
        REFERENCES EMPLEADO(id_empleado)
);
GO


-- =========================================
-- TABLA DETALLE_PEDIDO
-- =========================================

CREATE TABLE DETALLE_PEDIDO (
    id_pedido INT NOT NULL,
    num_linea INT NOT NULL,
    id_producto INT NOT NULL,
    cantidad INT NOT NULL CHECK (cantidad > 0),
    precio_unitario DECIMAL(10,2) NOT NULL CHECK (precio_unitario >= 0),
    estado_cocina VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE'
        CHECK (estado_cocina IN ('PENDIENTE', 'PREPARANDO', 'LISTO', 'ENTREGADO', 'CANCELADO')),
    notas VARCHAR(200),
    fecha_actualizacion DATETIME NOT NULL DEFAULT GETDATE(),

    PRIMARY KEY (id_pedido, num_linea),

    CONSTRAINT FK_DETALLE_PEDIDO_PEDIDO
        FOREIGN KEY (id_pedido)
        REFERENCES PEDIDO(id_pedido)
        ON DELETE CASCADE,

    CONSTRAINT FK_DETALLE_PEDIDO_PRODUCTO
        FOREIGN KEY (id_producto)
        REFERENCES PRODUCTO(id_producto)
);
GO


-- =========================================
-- TABLA TURNO_CAJA
-- =========================================

CREATE TABLE TURNO_CAJA (
    id_turno INT IDENTITY(1,1) PRIMARY KEY,
    id_empleado INT NOT NULL,
    fecha_apertura DATETIME NOT NULL DEFAULT GETDATE(),
    fecha_cierre DATETIME,
    monto_inicial DECIMAL(10,2) NOT NULL CHECK (monto_inicial >= 0),
    monto_final DECIMAL(10,2) CHECK (monto_final >= 0),

    CONSTRAINT CK_TURNO_CAJA_Cierre CHECK (fecha_cierre IS NULL OR (fecha_cierre >= fecha_apertura AND monto_final IS NOT NULL)),

    CONSTRAINT FK_TURNO_CAJA_EMPLEADO
        FOREIGN KEY (id_empleado)
        REFERENCES EMPLEADO(id_empleado)
);
GO


-- =========================================
-- TABLA FACTURA
-- =========================================

CREATE TABLE FACTURA (
    id_factura INT IDENTITY(1,1) PRIMARY KEY,
    numero_factura VARCHAR(50) UNIQUE NOT NULL,
    id_pedido INT UNIQUE NOT NULL,
    id_cliente INT,
    id_empleado INT NOT NULL,
    id_turno INT NOT NULL,
    fecha_hora DATETIME NOT NULL DEFAULT GETDATE(),
    subtotal DECIMAL(10,2) NOT NULL CHECK (subtotal >= 0),
    descuento DECIMAL(10,2) NOT NULL DEFAULT 0 CHECK (descuento >= 0),
    impuesto_ventas DECIMAL(10,2) NOT NULL CHECK (impuesto_ventas >= 0),
    servicio DECIMAL(10,2) NOT NULL CHECK (servicio >= 0),
    total DECIMAL(10,2) NOT NULL CHECK (total >= 0),
    vuelto DECIMAL(10,2) NOT NULL DEFAULT 0 CHECK (vuelto >= 0),
    estado VARCHAR(20) NOT NULL DEFAULT 'COBRADA' CHECK (estado IN ('COBRADA', 'ANULADA')),
    anulada_por INT,
    motivo_anulacion VARCHAR(255),
    fecha_anulacion DATETIME,

    CONSTRAINT CK_FACTURA_Total CHECK (descuento <= subtotal AND total = subtotal - descuento + impuesto_ventas + servicio),
    CONSTRAINT CK_FACTURA_Anulacion CHECK (estado <> 'ANULADA' OR (anulada_por IS NOT NULL AND motivo_anulacion IS NOT NULL AND fecha_anulacion IS NOT NULL)),

    CONSTRAINT FK_FACTURA_PEDIDO
        FOREIGN KEY (id_pedido)
        REFERENCES PEDIDO(id_pedido),

    CONSTRAINT FK_FACTURA_CLIENTE
        FOREIGN KEY (id_cliente)
        REFERENCES CLIENTE(id_cliente),

    CONSTRAINT FK_FACTURA_EMPLEADO
        FOREIGN KEY (id_empleado)
        REFERENCES EMPLEADO(id_empleado),

    CONSTRAINT FK_FACTURA_TURNO_CAJA
        FOREIGN KEY (id_turno)
        REFERENCES TURNO_CAJA(id_turno),

    CONSTRAINT FK_FACTURA_EMPLEADO_ANULA
        FOREIGN KEY (anulada_por)
        REFERENCES EMPLEADO(id_empleado)
);
GO


-- =========================================
-- TABLA PAGO
-- =========================================

CREATE TABLE PAGO (
    id_pago INT IDENTITY(1,1) PRIMARY KEY,
    id_factura INT NOT NULL,
    id_metodo_pago INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL CHECK (monto > 0),
    referencia VARCHAR(100),
    fecha_hora DATETIME NOT NULL DEFAULT GETDATE(),

    CONSTRAINT FK_PAGO_FACTURA
        FOREIGN KEY (id_factura)
        REFERENCES FACTURA(id_factura),

    CONSTRAINT FK_PAGO_METODO_PAGO
        FOREIGN KEY (id_metodo_pago)
        REFERENCES METODO_PAGO(id_metodo_pago)
);
GO


-- =========================================
-- TABLA AUDITORIA_PEDIDO
-- =========================================

CREATE TABLE AUDITORIA_PEDIDO (
    id_auditoria INT IDENTITY(1,1) PRIMARY KEY,
    id_pedido INT NOT NULL,
    accion VARCHAR(20) NOT NULL,
    usuario_bd VARCHAR(100) NOT NULL DEFAULT SUSER_SNAME(),
    id_empleado INT,
    fecha_hora DATETIME NOT NULL DEFAULT GETDATE(),
    estado_anterior VARCHAR(20),
    estado_nuevo VARCHAR(20),
    subtotal_anterior DECIMAL(10,2),
    subtotal_nuevo DECIMAL(10,2)
);
GO


-- =========================================
-- TABLA ALERTA_STOCK
-- =========================================

CREATE TABLE ALERTA_STOCK (
    id_alerta INT IDENTITY(1,1) PRIMARY KEY,
    id_insumo INT NOT NULL,
    stock_actual DECIMAL(10,2) NOT NULL,
    stock_minimo DECIMAL(10,2) NOT NULL,
    fecha_hora DATETIME NOT NULL DEFAULT GETDATE(),
    atendida BIT NOT NULL DEFAULT 0,

    CONSTRAINT FK_ALERTA_STOCK_INSUMO
        FOREIGN KEY (id_insumo)
        REFERENCES INSUMO(id_insumo)
);
GO


-- =========================================
-- TABLA RESPALDO_BD
-- =========================================

CREATE TABLE RESPALDO_BD (
    id_respaldo INT IDENTITY(1,1) PRIMARY KEY,
    nombre_archivo NVARCHAR(200) NOT NULL,
    ruta_completa NVARCHAR(460) NOT NULL,
    fecha_hora DATETIME NOT NULL DEFAULT GETDATE(),
    id_empleado INT,
    verificado BIT NOT NULL DEFAULT 0,
    resultado VARCHAR(500),

    CONSTRAINT FK_RESPALDO_BD_EMPLEADO
        FOREIGN KEY (id_empleado)
        REFERENCES EMPLEADO(id_empleado)
);
GO


-- =========================================
-- TABLA SESION_ACTIVA
-- =========================================

CREATE TABLE SESION_ACTIVA (
    id_sesion INT IDENTITY(1,1) PRIMARY KEY,
    id_empleado INT UNIQUE NOT NULL,
    token CHAR(64) NOT NULL,
    direccion_ip VARCHAR(45),
    navegador VARCHAR(255),
    fecha_inicio DATETIME NOT NULL DEFAULT GETDATE(),
    ultima_actividad DATETIME NOT NULL DEFAULT GETDATE(),

    CONSTRAINT FK_SESION_ACTIVA_EMPLEADO
        FOREIGN KEY (id_empleado)
        REFERENCES EMPLEADO(id_empleado)
        ON DELETE CASCADE
);
GO


-- =========================================
-- INDICES
-- =========================================

CREATE INDEX IX_DETALLE_PEDIDO_Cocina ON DETALLE_PEDIDO (estado_cocina, id_pedido);
CREATE INDEX IX_DETALLE_PEDIDO_Producto ON DETALLE_PEDIDO (id_producto) INCLUDE (cantidad, precio_unitario, estado_cocina);
CREATE INDEX IX_PEDIDO_MesaEstado ON PEDIDO (id_mesa, estado);
CREATE INDEX IX_PEDIDO_Estado_Fecha ON PEDIDO (estado, fecha_hora) INCLUDE (id_mesa, subtotal);
CREATE INDEX IX_FACTURA_FechaTotal ON FACTURA (fecha_hora) INCLUDE (estado, total);
CREATE INDEX IX_FACTURA_Turno ON FACTURA (id_turno) INCLUDE (estado, total, vuelto);
CREATE INDEX IX_PAGO_Factura ON PAGO (id_factura) INCLUDE (id_metodo_pago, monto);
CREATE INDEX IX_PRODUCTO_CategoriaActivo ON PRODUCTO (id_categoria, activo) INCLUDE (nombre_producto, precio);
CREATE INDEX IX_ALERTA_STOCK_Pendiente ON ALERTA_STOCK (atendida, id_insumo);
CREATE INDEX IX_ROL_PERMISO_Permiso ON ROL_PERMISO (id_permiso);
CREATE INDEX IX_AUDITORIA_PEDIDO_Pedido ON AUDITORIA_PEDIDO (id_pedido, fecha_hora);
GO

-- Un cajero solo puede tener un turno abierto a la vez
CREATE UNIQUE INDEX UQ_TURNO_CAJA_Abierto ON TURNO_CAJA (id_empleado)
    WHERE fecha_cierre IS NULL;
GO

-- Una mesa solo puede tener un pedido activo a la vez
CREATE UNIQUE INDEX UQ_PEDIDO_MesaAbierta ON PEDIDO (id_mesa)
    WHERE estado IN ('ABIERTO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO');
GO
