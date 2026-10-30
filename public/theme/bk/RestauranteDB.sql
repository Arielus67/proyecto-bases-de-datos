-- ============================================================================
-- PROYECTO FINAL: SISTEMA DE RESTAURANTE (IF0010 BASES DE DATOS II)
-- SCRIPT DDL, DML Y PROGRAMACIÓN T-SQL PARA MICROSOFT SQL SERVER
-- ============================================================================

CREATE DATABASE RestauranteDB;
GO

USE RestauranteDB;
GO

-- ============================================================================
-- 1. CREACIÓN DE TABLAS (TABLAS BASE Y SUBCLASES DE EMPLEADO)
-- ============================================================================

-- 1. ROL
CREATE TABLE ROL (
    id_rol INT IDENTITY(1,1) PRIMARY KEY,
    nombre_rol VARCHAR(50) NOT NULL UNIQUE
);

-- 2. ESTACION
CREATE TABLE ESTACION (
    id_estacion INT IDENTITY(1,1) PRIMARY KEY,
    nombre_estacion VARCHAR(50) NOT NULL UNIQUE
);

-- 3. EMPLEADO
CREATE TABLE EMPLEADO (
    id_empleado INT IDENTITY(1,1) PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    apellido VARCHAR(50) NOT NULL,
    correo VARCHAR(100) NOT NULL UNIQUE,
    contrasena_hash VARCHAR(255) NOT NULL,
    id_rol INT NOT NULL,
    fecha_ingreso DATETIME NOT NULL DEFAULT GETDATE(),
    activo BIT NOT NULL DEFAULT 1,
    CONSTRAINT FK_EMPLEADO_ROL FOREIGN KEY (id_rol) REFERENCES ROL(id_rol)
);

-- 4. MESERO (Subclase 1:1 con EMPLEADO)
CREATE TABLE MESERO (
    id_empleado INT PRIMARY KEY,
    zona_asignada VARCHAR(50) NOT NULL,
    CONSTRAINT FK_MESERO_EMPLEADO FOREIGN KEY (id_empleado) REFERENCES EMPLEADO(id_empleado) ON DELETE CASCADE
);

-- 5. COCINERO (Subclase 1:1 con EMPLEADO)
CREATE TABLE COCINERO (
    id_empleado INT PRIMARY KEY,
    id_estacion INT NOT NULL,
    especialidad VARCHAR(50) NOT NULL,
    CONSTRAINT FK_COCINERO_EMPLEADO FOREIGN KEY (id_empleado) REFERENCES EMPLEADO(id_empleado) ON DELETE CASCADE,
    CONSTRAINT FK_COCINERO_ESTACION FOREIGN KEY (id_estacion) REFERENCES ESTACION(id_estacion)
);

-- 6. CAJERO (Subclase 1:1 con EMPLEADO)
CREATE TABLE CAJERO (
    id_empleado INT PRIMARY KEY,
    codigo_caja VARCHAR(20) NOT NULL UNIQUE,
    CONSTRAINT FK_CAJERO_EMPLEADO FOREIGN KEY (id_empleado) REFERENCES EMPLEADO(id_empleado) ON DELETE CASCADE
);

-- 7. CATEGORIA
CREATE TABLE CATEGORIA (
    id_categoria INT IDENTITY(1,1) PRIMARY KEY,
    nombre_categoria VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(200) NULL
);

-- 8. MESA
CREATE TABLE MESA (
    id_mesa INT IDENTITY(1,1) PRIMARY KEY,
    numero_mesa INT NOT NULL UNIQUE,
    capacidad INT NOT NULL CHECK (capacidad > 0),
    zona VARCHAR(50) NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'DISPONIBLE'
        CHECK (estado IN ('DISPONIBLE', 'OCUPADA', 'RESERVADA', 'MANTENIMIENTO'))
);

-- 9. CLIENTE (Cédula cifrada con AES-256 / VARBINARY)
CREATE TABLE CLIENTE (
    id_cliente INT IDENTITY(1,1) PRIMARY KEY,
    cedula VARBINARY(256) NOT NULL,
    nombre VARCHAR(50) NOT NULL,
    apellido VARCHAR(50) NOT NULL,
    telefono VARCHAR(20) NULL,
    correo VARCHAR(100) NULL
);

-- 10. PRODUCTO
CREATE TABLE PRODUCTO (
    id_producto INT IDENTITY(1,1) PRIMARY KEY,
    nombre_producto VARCHAR(100) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL,
    precio DECIMAL(10,2) NOT NULL CHECK (precio >= 0),
    id_categoria INT NOT NULL,
    id_estacion INT NOT NULL,
    activo BIT NOT NULL DEFAULT 1,
    CONSTRAINT FK_PRODUCTO_CATEGORIA FOREIGN KEY (id_categoria) REFERENCES CATEGORIA(id_categoria),
    CONSTRAINT FK_PRODUCTO_ESTACION FOREIGN KEY (id_estacion) REFERENCES ESTACION(id_estacion)
);

-- 11. INSUMO
CREATE TABLE INSUMO (
    id_insumo INT IDENTITY(1,1) PRIMARY KEY,
    nombre_insumo VARCHAR(100) NOT NULL UNIQUE,
    unidad_medida VARCHAR(20) NOT NULL,
    stock_actual DECIMAL(10,2) NOT NULL CHECK (stock_actual >= 0),
    stock_minimo DECIMAL(10,2) NOT NULL CHECK (stock_minimo >= 0),
    costo_unitario DECIMAL(10,2) NOT NULL CHECK (costo_unitario >= 0)
);

-- 12. RECETA (Relación N:M entre PRODUCTO e INSUMO)
CREATE TABLE RECETA (
    id_producto INT NOT NULL,
    id_insumo INT NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL CHECK (cantidad > 0),
    PRIMARY KEY (id_producto, id_insumo),
    CONSTRAINT FK_RECETA_PRODUCTO FOREIGN KEY (id_producto) REFERENCES PRODUCTO(id_producto) ON DELETE CASCADE,
    CONSTRAINT FK_RECETA_INSUMO FOREIGN KEY (id_insumo) REFERENCES INSUMO(id_insumo)
);

-- 13. PEDIDO
CREATE TABLE PEDIDO (
    id_pedido INT IDENTITY(1,1) PRIMARY KEY,
    token_idempotencia VARCHAR(100) NOT NULL UNIQUE,
    id_mesa INT NOT NULL,
    id_empleado INT NOT NULL,
    fecha_hora DATETIME NOT NULL DEFAULT GETDATE(),
    estado VARCHAR(20) NOT NULL DEFAULT 'ABIERTO'
        CHECK (estado IN ('ABIERTO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO', 'FACTURADO', 'ANULADO')),
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00 CHECK (subtotal >= 0),
    observaciones VARCHAR(255) NULL,
    anulado_por INT NULL,
    motivo_anulacion VARCHAR(255) NULL,
    CONSTRAINT FK_PEDIDO_MESA FOREIGN KEY (id_mesa) REFERENCES MESA(id_mesa),
    CONSTRAINT FK_PEDIDO_MESERO FOREIGN KEY (id_empleado) REFERENCES EMPLEADO(id_empleado),
    CONSTRAINT FK_PEDIDO_ANULADO_POR FOREIGN KEY (anulado_por) REFERENCES EMPLEADO(id_empleado)
);

-- 14. DETALLE_PEDIDO (Entidad débil de PEDIDO)
CREATE TABLE DETALLE_PEDIDO (
    id_pedido INT NOT NULL,
    num_linea INT NOT NULL,
    id_producto INT NOT NULL,
    cantidad INT NOT NULL CHECK (cantidad > 0),
    precio_unitario DECIMAL(10,2) NOT NULL CHECK (precio_unitario >= 0),
    estado_cocina VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE'
        CHECK (estado_cocina IN ('PENDIENTE', 'PREPARANDO', 'LISTO', 'ENTREGADO', 'CANCELADO')),
    notas VARCHAR(200) NULL,
    fecha_actualizacion DATETIME NOT NULL DEFAULT GETDATE(),
    PRIMARY KEY (id_pedido, num_linea),
    CONSTRAINT FK_DETALLE_PEDIDO FOREIGN KEY (id_pedido) REFERENCES PEDIDO(id_pedido) ON DELETE CASCADE,
    CONSTRAINT FK_DETALLE_PRODUCTO FOREIGN KEY (id_producto) REFERENCES PRODUCTO(id_producto)
);

-- 15. TURNO_CAJA
CREATE TABLE TURNO_CAJA (
    id_turno INT IDENTITY(1,1) PRIMARY KEY,
    id_empleado INT NOT NULL,
    fecha_apertura DATETIME NOT NULL DEFAULT GETDATE(),
    fecha_cierre DATETIME NULL,
    monto_inicial DECIMAL(10,2) NOT NULL CHECK (monto_inicial >= 0),
    monto_final DECIMAL(10,2) NULL CHECK (monto_final >= 0),
    CONSTRAINT FK_TURNO_CAJERO FOREIGN KEY (id_empleado) REFERENCES EMPLEADO(id_empleado)
);

-- 16. FACTURA
CREATE TABLE FACTURA (
    id_factura INT IDENTITY(1,1) PRIMARY KEY,
    numero_factura VARCHAR(50) NOT NULL UNIQUE,
    id_pedido INT NOT NULL UNIQUE,
    id_cliente INT NULL,
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
    CONSTRAINT FK_FACTURA_PEDIDO FOREIGN KEY (id_pedido) REFERENCES PEDIDO(id_pedido),
    CONSTRAINT FK_FACTURA_CLIENTE FOREIGN KEY (id_cliente) REFERENCES CLIENTE(id_cliente),
    CONSTRAINT FK_FACTURA_CAJERO FOREIGN KEY (id_empleado) REFERENCES EMPLEADO(id_empleado),
    CONSTRAINT FK_FACTURA_TURNO FOREIGN KEY (id_turno) REFERENCES TURNO_CAJA(id_turno)
);

-- 17. METODO_PAGO
CREATE TABLE METODO_PAGO (
    id_metodo_pago INT IDENTITY(1,1) PRIMARY KEY,
    nombre_metodo VARCHAR(50) NOT NULL UNIQUE
);

-- 18. PAGO
CREATE TABLE PAGO (
    id_pago INT IDENTITY(1,1) PRIMARY KEY,
    id_factura INT NOT NULL,
    id_metodo_pago INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL CHECK (monto > 0),
    referencia VARCHAR(100) NULL,
    fecha_hora DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_PAGO_FACTURA FOREIGN KEY (id_factura) REFERENCES FACTURA(id_factura),
    CONSTRAINT FK_PAGO_METODO FOREIGN KEY (id_metodo_pago) REFERENCES METODO_PAGO(id_metodo_pago)
);

-- 19. AUDITORIA_PEDIDO
CREATE TABLE AUDITORIA_PEDIDO (
    id_auditoria INT IDENTITY(1,1) PRIMARY KEY,
    id_pedido INT NOT NULL,
    accion VARCHAR(20) NOT NULL,
    usuario_bd VARCHAR(100) NOT NULL DEFAULT SUSER_SNAME(),
    id_empleado_app INT NULL,
    fecha_hora DATETIME NOT NULL DEFAULT GETDATE(),
    estado_anterior VARCHAR(20) NULL,
    estado_nuevo VARCHAR(20) NULL,
    subtotal_anterior DECIMAL(10,2) NULL,
    subtotal_nuevo DECIMAL(10,2) NULL
);

-- 20. ALERTA_STOCK
CREATE TABLE ALERTA_STOCK (
    id_alerta INT IDENTITY(1,1) PRIMARY KEY,
    id_insumo INT NOT NULL,
    stock_actual DECIMAL(10,2) NOT NULL,
    stock_minimo DECIMAL(10,2) NOT NULL,
    fecha_hora DATETIME NOT NULL DEFAULT GETDATE(),
    atendida BIT NOT NULL DEFAULT 0,
    CONSTRAINT FK_ALERTA_INSUMO FOREIGN KEY (id_insumo) REFERENCES INSUMO(id_insumo)
);
GO

-- ============================================================================
-- 2. ÍNDICES DE RENDIMIENTO Y RESTRICCIONES DE CONCURRENCIA
-- ============================================================================

-- Índice para acelerar la cola de cocina
CREATE NONCLUSTERED INDEX IX_DETALLE_PEDIDO_Cocina
ON DETALLE_PEDIDO (estado_cocina, id_pedido);

-- Índice para verificar rápidamente si una mesa tiene pedido abierto
CREATE NONCLUSTERED INDEX IX_PEDIDO_MesaEstado
ON PEDIDO (id_mesa, estado);

-- Índice de cobertura para reportes de ventas por rango de fechas
CREATE NONCLUSTERED INDEX IX_FACTURA_FechaTotal
ON FACTURA (fecha_hora)
INCLUDE (estado, total);

-- Índice de cobertura para menú por categoría
CREATE NONCLUSTERED INDEX IX_PRODUCTO_CategoriaActivo
ON PRODUCTO (id_categoria, activo)
INCLUDE (nombre_producto, precio);

-- Restricción UNIQUE filtrada: un cajero solo puede tener UN turno abierto a la vez
CREATE UNIQUE NONCLUSTERED INDEX UQ_TURNO_CAJA_Abierto
ON TURNO_CAJA (id_empleado)
WHERE fecha_cierre IS NULL;
GO

-- ============================================================================
-- 3. VISTAS DEL SISTEMA
-- ============================================================================

-- Menú disponible y activo
CREATE VIEW vw_menu_activo AS
SELECT
    p.id_producto,
    p.nombre_producto,
    p.descripcion,
    p.precio,
    c.nombre_categoria,
    e.nombre_estacion
FROM PRODUCTO p
INNER JOIN CATEGORIA c ON p.id_categoria = c.id_categoria
INNER JOIN ESTACION e ON p.id_estacion = e.id_estacion
WHERE p.activo = 1;
GO

-- Estado dinámico de mesas
CREATE VIEW vw_mesas_estado AS
SELECT
    m.id_mesa,
    m.numero_mesa,
    m.capacidad,
    m.zona,
    m.estado AS estado_mesa,
    p.id_pedido,
    p.fecha_hora AS fecha_pedido,
    p.subtotal
FROM MESA m
LEFT JOIN PEDIDO p ON m.id_mesa = p.id_mesa AND p.estado IN ('ABIERTO', 'EN_PREPARACION', 'LISTO');
GO

-- Cola de cocina por estación
CREATE VIEW vw_cola_cocina AS
SELECT
    dp.id_pedido,
    m.numero_mesa,
    p.nombre_producto,
    dp.cantidad,
    dp.estado_cocina,
    dp.notas,
    e.nombre_estacion,
    dp.fecha_actualizacion
FROM DETALLE_PEDIDO dp
INNER JOIN PRODUCTO p ON dp.id_producto = p.id_producto
INNER JOIN ESTACION e ON p.id_estacion = e.id_estacion
INNER JOIN PEDIDO ped ON dp.id_pedido = ped.id_pedido
INNER JOIN MESA m ON ped.id_mesa = m.id_mesa
WHERE dp.estado_cocina IN ('PENDIENTE', 'PREPARANDO');
GO

-- Vista para impresión de tickets de caja
CREATE VIEW vw_factura_ticket AS
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
FROM FACTURA f
INNER JOIN PEDIDO p ON f.id_pedido = p.id_pedido
INNER JOIN MESA m ON p.id_mesa = m.id_mesa
INNER JOIN EMPLEADO emp ON f.id_empleado = emp.id_empleado;
GO

-- ============================================================================
-- 4. TRIGGERS DEL SISTEMA
-- ============================================================================

-- Trigger INSTEAD OF DELETE en PRODUCTO (Baja lógica)
CREATE TRIGGER trg_producto_baja_logica
ON PRODUCTO
INSTEAD OF DELETE
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE PRODUCTO
    SET activo = 0
    WHERE id_producto IN (SELECT id_producto FROM deleted);
END;
GO

-- Trigger AFTER INSERT, UPDATE, DELETE en PEDIDO (Bitácora de Auditoría)
CREATE TRIGGER trg_pedido_auditoria
ON PEDIDO
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;

    -- Caso INSERT
    INSERT INTO AUDITORIA_PEDIDO (id_pedido, accion, usuario_bd, id_empleado_app, estado_nuevo, subtotal_nuevo)
    SELECT id_pedido, 'INSERT', SUSER_SNAME(), id_empleado, estado, subtotal
    FROM inserted
    WHERE id_pedido NOT IN (SELECT id_pedido FROM deleted);

    -- Caso UPDATE
    INSERT INTO AUDITORIA_PEDIDO (id_pedido, accion, usuario_bd, id_empleado_app, estado_anterior, estado_nuevo, subtotal_anterior, subtotal_nuevo)
    SELECT i.id_pedido, 'UPDATE', SUSER_SNAME(), i.id_empleado, d.estado, i.estado, d.subtotal, i.subtotal
    FROM inserted i
    INNER JOIN deleted d ON i.id_pedido = d.id_pedido;

    -- Caso DELETE
    INSERT INTO AUDITORIA_PEDIDO (id_pedido, accion, usuario_bd, id_empleado_app, estado_anterior, subtotal_anterior)
    SELECT id_pedido, 'DELETE', SUSER_SNAME(), id_empleado, estado, subtotal
    FROM deleted
    WHERE id_pedido NOT IN (SELECT id_pedido FROM inserted);
END;
GO

-- Trigger AFTER UPDATE en INSUMO (Alerta cuando stock_actual <= stock_minimo)
CREATE TRIGGER trg_insumo_alerta_stock
ON INSUMO
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO ALERTA_STOCK (id_insumo, stock_actual, stock_minimo, fecha_hora, atendida)
    SELECT i.id_insumo, i.stock_actual, i.stock_minimo, GETDATE(), 0
    FROM inserted i
    INNER JOIN deleted d ON i.id_insumo = d.id_insumo
    WHERE i.stock_actual <= i.stock_minimo
      AND d.stock_actual > d.stock_minimo;
END;
GO

CREATE TRIGGER trg_pedido_libera_mesa
ON PEDIDO
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE m
    SET m.estado = 'DISPONIBLE'
    FROM MESA m
    INNER JOIN inserted i ON i.id_mesa = m.id_mesa
    INNER JOIN deleted d ON d.id_pedido = i.id_pedido
    WHERE i.estado IN ('FACTURADO', 'ANULADO')
      AND d.estado NOT IN ('FACTURADO', 'ANULADO')
      AND m.estado = 'OCUPADA'
      AND NOT EXISTS (
          SELECT 1 FROM PEDIDO p
          WHERE p.id_mesa = m.id_mesa
            AND p.id_pedido <> i.id_pedido
            AND p.estado IN ('ABIERTO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO')
      );
END;
GO
-- ============================================================================
-- 5. PROCEDIMIENTOS ALMACENADOS COMPLEJOS Y NO BÁSICOS
-- ============================================================================

-- Procedimiento 1: Registrar Pedido Concurrente e Idempotente con JSON
CREATE OR ALTER PROCEDURE sp_registrar_pedido
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

    -- Idempotencia: si el token ya fue procesado, retornar el pedido existente
    IF EXISTS (SELECT 1 FROM PEDIDO WHERE token_idempotencia = @token_idempotencia)
    BEGIN
        SELECT @nuevo_id_pedido = id_pedido
        FROM PEDIDO
        WHERE token_idempotencia = @token_idempotencia;
        RETURN 1;
    END

    BEGIN TRY
        BEGIN TRANSACTION;

        -- Bloqueo pesimista sobre la mesa para prevenir colisión de pedidos concurrentes
        DECLARE @estado_mesa VARCHAR(20);
        SELECT @estado_mesa = estado
        FROM MESA WITH (UPDLOCK, HOLDLOCK)
        WHERE id_mesa = @id_mesa;

        IF @estado_mesa IS NULL
            THROW 50001, 'La mesa especificada no existe.', 1;

        -- NUEVO: la mesa debe estar disponible para recibir un pedido
        IF @estado_mesa <> 'DISPONIBLE'
        BEGIN
            DECLARE @msg NVARCHAR(200) =
                'La mesa no está disponible (estado actual: ' + @estado_mesa + ').';
            THROW 50007, @msg, 1;
        END

        -- Insertar cabecera del pedido
        INSERT INTO PEDIDO (token_idempotencia, id_mesa, id_empleado, observaciones)
        VALUES (@token_idempotencia, @id_mesa, @id_empleado, @observaciones);

        SET @nuevo_id_pedido = SCOPE_IDENTITY();

        -- Parsear JSON de líneas de detalle e insertar
        INSERT INTO DETALLE_PEDIDO (id_pedido, num_linea, id_producto, cantidad, precio_unitario, notas)
        SELECT
            @nuevo_id_pedido,
            JSON_VALUE(j.value, '$.num_linea'),
            JSON_VALUE(j.value, '$.id_producto'),
            JSON_VALUE(j.value, '$.cantidad'),
            p.precio,
            JSON_VALUE(j.value, '$.notas')
        FROM OPENJSON(@json_lineas) j
        INNER JOIN PRODUCTO p ON p.id_producto = CAST(JSON_VALUE(j.value, '$.id_producto') AS INT);

        -- Calcular subtotal
        UPDATE PEDIDO
        SET subtotal = (SELECT ISNULL(SUM(cantidad * precio_unitario), 0)
                        FROM DETALLE_PEDIDO WHERE id_pedido = @nuevo_id_pedido)
        WHERE id_pedido = @nuevo_id_pedido;

        -- Descontar inventario según la receta de los productos
        UPDATE i
        SET i.stock_actual = i.stock_actual - (dp.cantidad * r.cantidad)
        FROM INSUMO i WITH (UPDLOCK)
        INNER JOIN RECETA r ON i.id_insumo = r.id_insumo
        INNER JOIN DETALLE_PEDIDO dp ON r.id_producto = dp.id_producto
        WHERE dp.id_pedido = @nuevo_id_pedido;

        -- Validar que no haya quedado stock negativo
        IF EXISTS (
            SELECT 1 FROM INSUMO i
            INNER JOIN RECETA r ON i.id_insumo = r.id_insumo
            INNER JOIN DETALLE_PEDIDO dp ON r.id_producto = dp.id_producto
            WHERE dp.id_pedido = @nuevo_id_pedido AND i.stock_actual < 0
        )
        BEGIN
            THROW 50002, 'Stock insuficiente de insumos para completar el pedido.', 1;
        END

        -- Actualizar estado de la mesa
        UPDATE MESA SET estado = 'OCUPADA' WHERE id_mesa = @id_mesa;

        COMMIT TRANSACTION;
        RETURN 0;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

-- Procedimiento 2: Cobrar Cuenta y Emitir Factura
CREATE PROCEDURE sp_cobrar_cuenta
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

    BEGIN TRY
        BEGIN TRANSACTION;

        DECLARE @subtotal DECIMAL(10,2), @id_mesa INT, @id_turno INT;

        -- Validar pedido con bloqueo
        SELECT @subtotal = subtotal, @id_mesa = id_mesa
        FROM PEDIDO WITH (UPDLOCK)
        WHERE id_pedido = @id_pedido AND estado IN ('ABIERTO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO');

        IF @subtotal IS NULL
            THROW 50003, 'El pedido no existe o ya fue facturado/anulado.', 1;

        -- Obtener turno abierto del cajero
        SELECT @id_turno = id_turno
        FROM TURNO_CAJA
        WHERE id_empleado = @id_cajero AND fecha_cierre IS NULL;

        IF @id_turno IS NULL
            THROW 50004, 'El cajero no tiene un turno de caja abierto.', 1;

        -- Cálculos tributarios (IVA 13%, Servicio 10%)
        DECLARE @monto_descuento DECIMAL(10,2) = @subtotal * (@descuento / 100.00);
        DECLARE @base_imponible DECIMAL(10,2) = @subtotal - @monto_descuento;
        DECLARE @iva DECIMAL(10,2) = @base_imponible * 0.13;
        DECLARE @servicio DECIMAL(10,2) = @base_imponible * 0.10;
        DECLARE @total DECIMAL(10,2) = @base_imponible + @iva + @servicio;

        -- Validar total cobrado en pagos mixtos
        DECLARE @total_pagado DECIMAL(10,2);
        SELECT @total_pagado = SUM(CAST(JSON_VALUE(value, '$.monto') AS DECIMAL(10,2)))
        FROM OPENJSON(@json_pagos);

        IF @total_pagado < @total
            THROW 50005, 'El monto acumulado de pagos es inferior al total de la factura.', 1;

        DECLARE @vuelto DECIMAL(10,2) = @total_pagado - @total;

        -- Generar número consecutivo de ticket
        SET @numero_factura_out = 'FAC-' + RIGHT('00000000' + CAST(@id_pedido AS VARCHAR(8)), 8);

        -- Registrar factura
        INSERT INTO FACTURA (numero_factura, id_pedido, id_cliente, id_empleado, id_turno, subtotal, descuento, impuesto_ventas, servicio, total, vuelto)
        VALUES (@numero_factura_out, @id_pedido, @id_cliente, @id_cajero, @id_turno, @subtotal, @monto_descuento, @iva, @servicio, @total, @vuelto);

        DECLARE @id_factura INT = SCOPE_IDENTITY();

        -- Registrar deslose de pagos
        INSERT INTO PAGO (id_factura, id_metodo_pago, monto, referencia)
        SELECT
            @id_factura,
            CAST(JSON_VALUE(value, '$.id_metodo_pago') AS INT),
            CAST(JSON_VALUE(value, '$.monto') AS DECIMAL(10,2)),
            JSON_VALUE(value, '$.referencia')
        FROM OPENJSON(@json_pagos);

        -- Actualizar estado del pedido y liberar mesa
        UPDATE PEDIDO SET estado = 'FACTURADO' WHERE id_pedido = @id_pedido;
        UPDATE MESA SET estado = 'DISPONIBLE' WHERE id_mesa = @id_mesa;

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

-- Procedimiento 3: Anular Pedido y Restituir Insumos No Preparados
CREATE PROCEDURE sp_anular_pedido
    @id_pedido INT,
    @id_empleado INT,
    @motivo VARCHAR(255)
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    BEGIN TRY
        BEGIN TRANSACTION;

        DECLARE @id_mesa INT, @estado_pedido VARCHAR(20);
        SELECT @id_mesa = id_mesa, @estado_pedido = estado
        FROM PEDIDO WITH (UPDLOCK)
        WHERE id_pedido = @id_pedido;

        IF @estado_pedido IS NULL OR @estado_pedido = 'FACTURADO'
            THROW 50006, 'No se puede anular un pedido inexistente o ya facturado.', 1;

        -- Reintegrar insumos únicamente para ítems con estado_cocina PENDIENTE
        UPDATE i
        SET i.stock_actual = i.stock_actual + (dp.cantidad * r.cantidad)
        FROM INSUMO i
        INNER JOIN RECETA r ON i.id_insumo = r.id_insumo
        INNER JOIN DETALLE_PEDIDO dp ON r.id_producto = dp.id_producto
        WHERE dp.id_pedido = @id_pedido AND dp.estado_cocina = 'PENDIENTE';

        -- Marcar pedido como ANULADO
        UPDATE PEDIDO
        SET estado = 'ANULADO',
            anulado_por = @id_empleado,
            motivo_anulacion = @motivo
        WHERE id_pedido = @id_pedido;

        -- Cancelar líneas del detalle
        UPDATE DETALLE_PEDIDO
        SET estado_cocina = 'CANCELADO'
        WHERE id_pedido = @id_pedido AND estado_cocina = 'PENDIENTE';

        -- Liberar mesa
        UPDATE MESA SET estado = 'DISPONIBLE' WHERE id_mesa = @id_mesa;

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

-- Procedimiento 4: Reporte de Ventas Agrupado
CREATE PROCEDURE sp_reporte_ventas
    @fecha_inicio DATETIME,
    @fecha_fin DATETIME,
    @agrupar_por VARCHAR(20) -- 'DIA', 'PRODUCTO', 'MESERO', 'PAGO'
AS
BEGIN
    SET NOCOUNT ON;
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
        FROM FACTURA f
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
        FROM DETALLE_PEDIDO dp
        INNER JOIN PRODUCTO p ON dp.id_producto = p.id_producto
        INNER JOIN PEDIDO ped ON dp.id_pedido = ped.id_pedido
        INNER JOIN FACTURA f ON ped.id_pedido = f.id_pedido
        WHERE f.fecha_hora BETWEEN @fecha_inicio AND @fecha_fin AND f.estado = 'COBRADA'
        GROUP BY p.nombre_producto;
    END
    ELSE IF @agrupar_por = 'MESERO'
    BEGIN
        SELECT
            e.nombre + ' ' + e.apellido AS Mesero,
            COUNT(DISTINCT p.id_pedido) AS Pedidos_Atendidos,
            SUM(f.total) AS Total_Vendido
        FROM FACTURA f
        INNER JOIN PEDIDO p ON f.id_pedido = p.id_pedido
        INNER JOIN EMPLEADO e ON p.id_empleado = e.id_empleado
        WHERE f.fecha_hora BETWEEN @fecha_inicio AND @fecha_fin AND f.estado = 'COBRADA'
        GROUP BY e.nombre, e.apellido
        ORDER BY Total_Vendido DESC;
    END
    ELSE IF @agrupar_por = 'PAGO'
    BEGIN
        SELECT
            mp.nombre_metodo,
            COUNT(pg.id_pago) AS Transacciones,
            SUM(pg.monto) AS Total_Recaudado
        FROM PAGO pg
        INNER JOIN METODO_PAGO mp ON pg.id_metodo_pago = mp.id_metodo_pago
        INNER JOIN FACTURA f ON pg.id_factura = f.id_factura
        WHERE f.fecha_hora BETWEEN @fecha_inicio AND @fecha_fin AND f.estado = 'COBRADA'
        GROUP BY mp.nombre_metodo;
    END
END;
GO

-- ============================================================================
-- 6. CONFIGURACIÓN DE SEGURIDAD (ROLES Y PERMISOS)
-- ============================================================================

-- Crear Roles de Base de Datos
CREATE ROLE rol_administrador;
CREATE ROLE rol_mesero;
CREATE ROLE rol_cocinero;
CREATE ROLE rol_cajero;
GO

-- Permisos para Administrador
GRANT EXECUTE ON SCHEMA::dbo TO rol_administrador;
GRANT SELECT ON SCHEMA::dbo TO rol_administrador;

-- Permisos para Mesero
GRANT SELECT ON vw_menu_activo TO rol_mesero;
GRANT SELECT ON vw_mesas_estado TO rol_mesero;
GRANT EXECUTE ON sp_registrar_pedido TO rol_mesero;

-- Permisos para Cocinero
GRANT SELECT ON vw_cola_cocina TO rol_cocinero;

-- Permisos para Cajero
GRANT SELECT ON vw_mesas_estado TO rol_cajero;
GRANT SELECT ON vw_factura_ticket TO rol_cajero;
GRANT EXECUTE ON sp_cobrar_cuenta TO rol_cajero;
GRANT EXECUTE ON sp_anular_pedido TO rol_cajero;
GO
