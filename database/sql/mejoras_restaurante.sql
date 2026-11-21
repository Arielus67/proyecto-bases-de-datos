SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO

IF OBJECT_ID(N'dbo.RESPALDO_BD', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.RESPALDO_BD (
        id_respaldo INT IDENTITY(1,1) PRIMARY KEY,
        nombre_archivo NVARCHAR(200) NOT NULL,
        ruta_completa NVARCHAR(460) NOT NULL,
        fecha_hora DATETIME NOT NULL CONSTRAINT DF_RESPALDO_BD_fecha DEFAULT GETDATE(),
        id_empleado INT NULL,
        verificado BIT NOT NULL CONSTRAINT DF_RESPALDO_BD_verificado DEFAULT 0,
        resultado VARCHAR(500) NULL,
        CONSTRAINT FK_RESPALDO_BD_EMPLEADO FOREIGN KEY (id_empleado) REFERENCES dbo.EMPLEADO(id_empleado)
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UQ_PEDIDO_MesaAbierta' AND object_id = OBJECT_ID(N'dbo.PEDIDO'))
BEGIN
    IF EXISTS (
        SELECT id_mesa
        FROM dbo.PEDIDO
        WHERE estado IN ('ABIERTO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO')
        GROUP BY id_mesa
        HAVING COUNT(*) > 1
    )
    BEGIN
        PRINT 'AVISO: hay mesas con mas de un pedido abierto. Corrigelas y vuelve a ejecutar para crear UQ_PEDIDO_MesaAbierta.';
    END
    ELSE
    BEGIN
        CREATE UNIQUE NONCLUSTERED INDEX UQ_PEDIDO_MesaAbierta
        ON dbo.PEDIDO (id_mesa)
        WHERE estado IN ('ABIERTO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO');
    END
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_PEDIDO_Estado_Fecha' AND object_id = OBJECT_ID(N'dbo.PEDIDO'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_PEDIDO_Estado_Fecha
    ON dbo.PEDIDO (estado, fecha_hora)
    INCLUDE (id_mesa, subtotal);
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_FACTURA_Turno' AND object_id = OBJECT_ID(N'dbo.FACTURA'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_FACTURA_Turno
    ON dbo.FACTURA (id_turno)
    INCLUDE (estado, total, vuelto);
END;
GO

CREATE OR ALTER TRIGGER dbo.trg_insumo_alerta_alta
ON dbo.INSUMO
AFTER INSERT
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.ALERTA_STOCK (id_insumo, stock_actual, stock_minimo, fecha_hora, atendida)
    SELECT id_insumo, stock_actual, stock_minimo, GETDATE(), 0
    FROM inserted
    WHERE stock_actual <= stock_minimo;
END;
GO

CREATE OR ALTER TRIGGER dbo.trg_insumo_alertas_reposicion
ON dbo.INSUMO
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    IF NOT UPDATE(stock_actual) AND NOT UPDATE(stock_minimo)
        RETURN;

    UPDATE a
    SET a.atendida = 1
    FROM dbo.ALERTA_STOCK a
    INNER JOIN inserted i ON i.id_insumo = a.id_insumo
    WHERE a.atendida = 0
      AND i.stock_actual > i.stock_minimo;
END;
GO

CREATE OR ALTER TRIGGER dbo.trg_pedido_estado_final
ON dbo.PEDIDO
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    IF NOT UPDATE(estado)
        RETURN;

    IF EXISTS (
        SELECT 1
        FROM inserted i
        INNER JOIN deleted d ON d.id_pedido = i.id_pedido
        WHERE d.estado IN ('FACTURADO', 'ANULADO')
          AND i.estado <> d.estado
    )
    BEGIN
        ROLLBACK TRANSACTION;
        THROW 50013, 'Un pedido facturado o anulado no puede cambiar de estado.', 1;
    END
END;
GO

CREATE OR ALTER TRIGGER dbo.trg_factura_no_eliminar
ON dbo.FACTURA
INSTEAD OF DELETE
AS
BEGIN
    SET NOCOUNT ON;

    IF EXISTS (SELECT 1 FROM deleted)
        THROW 50010, 'Las facturas no se eliminan. Anule la factura en su lugar.', 1;
END;
GO

CREATE OR ALTER TRIGGER dbo.trg_factura_inmutable
ON dbo.FACTURA
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;

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
    BEGIN
        ROLLBACK TRANSACTION;
        THROW 50011, 'Los datos de una factura emitida no se pueden modificar. Solo se permite anularla.', 1;
    END
END;
GO

CREATE OR ALTER TRIGGER dbo.trg_pago_inmutable
ON dbo.PAGO
AFTER UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;

    IF EXISTS (SELECT 1 FROM deleted)
    BEGIN
        ROLLBACK TRANSACTION;
        THROW 50012, 'Los pagos registrados no se pueden modificar ni eliminar.', 1;
    END
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_abrir_turno
    @id_empleado INT,
    @monto_inicial DECIMAL(10,2),
    @id_turno INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    BEGIN TRY
        BEGIN TRANSACTION;

        IF @monto_inicial IS NULL OR @monto_inicial < 0
            THROW 50020, 'El monto inicial no es valido.', 1;

        IF NOT EXISTS (
            SELECT 1
            FROM dbo.CAJERO c
            INNER JOIN dbo.EMPLEADO e ON e.id_empleado = c.id_empleado
            WHERE c.id_empleado = @id_empleado AND e.activo = 1
        )
            THROW 50021, 'El empleado no es un cajero activo.', 1;

        IF EXISTS (
            SELECT 1
            FROM dbo.TURNO_CAJA WITH (UPDLOCK, HOLDLOCK)
            WHERE id_empleado = @id_empleado AND fecha_cierre IS NULL
        )
            THROW 50022, 'Ese cajero ya tiene un turno abierto.', 1;

        INSERT INTO dbo.TURNO_CAJA (id_empleado, monto_inicial)
        VALUES (@id_empleado, @monto_inicial);

        SET @id_turno = SCOPE_IDENTITY();

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

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

    IF @ruta IS NULL OR LEN(LTRIM(RTRIM(@ruta))) = 0
        THROW 50030, 'Debe indicar la carpeta de respaldos.', 1;

    SET @ruta = LTRIM(RTRIM(@ruta));

    IF RIGHT(@ruta, 1) NOT IN ('\', '/')
        SET @ruta = @ruta + N'\';

    SET @nombre = @bd + N'_' + FORMAT(SYSDATETIME(), 'yyyyMMdd_HHmmss_fff') + N'.bak';
    SET @archivo = @ruta + @nombre;

    BEGIN TRY
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

        INSERT INTO dbo.RESPALDO_BD (nombre_archivo, ruta_completa, id_empleado, verificado, resultado)
        VALUES (@nombre, @archivo, @id_empleado, 0, @mensaje);

        THROW;
    END CATCH
END;
GO

CREATE OR ALTER PROCEDURE dbo.sp_verificar_respaldo
    @id_respaldo INT
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @archivo NVARCHAR(460);
    DECLARE @mensaje VARCHAR(500);

    SELECT @archivo = ruta_completa
    FROM dbo.RESPALDO_BD
    WHERE id_respaldo = @id_respaldo;

    IF @archivo IS NULL
        THROW 50031, 'El respaldo indicado no existe en el historial.', 1;

    BEGIN TRY
        RESTORE VERIFYONLY FROM DISK = @archivo WITH CHECKSUM;

        UPDATE dbo.RESPALDO_BD
        SET verificado = 1,
            resultado = 'Verificacion de integridad correcta.'
        WHERE id_respaldo = @id_respaldo;
    END TRY
    BEGIN CATCH
        SET @mensaje = LEFT(ERROR_MESSAGE(), 500);

        UPDATE dbo.RESPALDO_BD
        SET verificado = 0,
            resultado = @mensaje
        WHERE id_respaldo = @id_respaldo;

        THROW;
    END CATCH
END;
GO

IF DATABASE_PRINCIPAL_ID(N'rol_administrador') IS NULL CREATE ROLE rol_administrador;
IF DATABASE_PRINCIPAL_ID(N'rol_mesero') IS NULL CREATE ROLE rol_mesero;
IF DATABASE_PRINCIPAL_ID(N'rol_cocinero') IS NULL CREATE ROLE rol_cocinero;
IF DATABASE_PRINCIPAL_ID(N'rol_cajero') IS NULL CREATE ROLE rol_cajero;
GO

GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON SCHEMA::dbo TO rol_administrador;

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
IF OBJECT_ID(N'dbo.sp_agregar_items_pedido', N'P') IS NOT NULL
    GRANT EXECUTE ON dbo.sp_agregar_items_pedido TO rol_mesero;

GRANT SELECT ON dbo.PEDIDO TO rol_cocinero;
GRANT SELECT ON dbo.DETALLE_PEDIDO TO rol_cocinero;
GRANT SELECT ON dbo.PRODUCTO TO rol_cocinero;
GRANT SELECT ON dbo.MESA TO rol_cocinero;
GRANT SELECT ON dbo.vw_cola_cocina TO rol_cocinero;
GRANT UPDATE (estado) ON dbo.PEDIDO TO rol_cocinero;
GRANT UPDATE (estado_cocina, fecha_actualizacion) ON dbo.DETALLE_PEDIDO TO rol_cocinero;

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
GO

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
