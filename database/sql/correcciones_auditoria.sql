/* ============================================================================
   CORRECCIONES DE AUDITORÍA (parche idempotente)
   Ejecutar DESPUÉS de RestauranteDB.sql y, opcionalmente, de datos_iniciales.sql.
   Cada bloque corresponde a un ID del informe de auditoría.

   ADVERTENCIA: este parche no se ha ejecutado contra SQL Server en el entorno de
   auditoría (no hay servidor disponible). Pruébelo primero en una base de copia.
   ============================================================================ */
USE RestauranteDB;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

BEGIN TRY
    BEGIN TRANSACTION;

    /* ------------------------------------------------------------------------
       ERROR 004 · Un pedido solo entra a FACTURADO con factura cobrada, y
       sale de FACTURADO únicamente hacia LISTO cuando la factura fue anulada.
       ------------------------------------------------------------------------ */
    EXEC(N'
    CREATE OR ALTER TRIGGER dbo.trg_pedido_estado_final
    ON dbo.PEDIDO
    AFTER UPDATE
    AS
    BEGIN
        SET NOCOUNT ON;

        IF NOT UPDATE(estado)
            RETURN;

        -- ANULADO es definitivo
        IF EXISTS (
            SELECT 1 FROM inserted i INNER JOIN deleted d ON d.id_pedido = i.id_pedido
            WHERE d.estado = ''ANULADO'' AND i.estado <> d.estado)
        BEGIN
            ROLLBACK TRANSACTION;
            THROW 50013, ''Un pedido anulado no puede cambiar de estado.'', 1;
        END

        -- FACTURADO solo puede pasar a LISTO, y únicamente si ya no hay factura cobrada
        IF EXISTS (
            SELECT 1 FROM inserted i INNER JOIN deleted d ON d.id_pedido = i.id_pedido
            WHERE d.estado = ''FACTURADO'' AND i.estado NOT IN (''FACTURADO'', ''LISTO''))
           OR EXISTS (
            SELECT 1 FROM inserted i INNER JOIN deleted d ON d.id_pedido = i.id_pedido
            WHERE d.estado = ''FACTURADO'' AND i.estado = ''LISTO''
              AND EXISTS (SELECT 1 FROM dbo.FACTURA f WHERE f.id_pedido = i.id_pedido AND f.estado = ''COBRADA''))
        BEGIN
            ROLLBACK TRANSACTION;
            THROW 50013, ''Un pedido facturado solo puede reabrirse si su factura fue anulada.'', 1;
        END

        -- No se puede marcar FACTURADO sin una factura cobrada (sp_cobrar_cuenta inserta la factura antes)
        IF EXISTS (
            SELECT 1 FROM inserted i INNER JOIN deleted d ON d.id_pedido = i.id_pedido
            WHERE i.estado = ''FACTURADO'' AND d.estado <> ''FACTURADO''
              AND NOT EXISTS (SELECT 1 FROM dbo.FACTURA f WHERE f.id_pedido = i.id_pedido AND f.estado = ''COBRADA''))
        BEGIN
            ROLLBACK TRANSACTION;
            THROW 50014, ''No se puede facturar un pedido sin una factura cobrada.'', 1;
        END
    END;');

    /* ------------------------------------------------------------------------
       ERROR 005 y 006 · Un turno cerrado no se modifica ni se elimina.
       ------------------------------------------------------------------------ */
    EXEC(N'
    CREATE OR ALTER TRIGGER dbo.trg_turno_caja_cerrado
    ON dbo.TURNO_CAJA
    AFTER UPDATE, DELETE
    AS
    BEGIN
        SET NOCOUNT ON;

        IF EXISTS (SELECT 1 FROM deleted d WHERE d.fecha_cierre IS NOT NULL)
        BEGIN
            ROLLBACK TRANSACTION;
            THROW 50015, ''El turno ya está cerrado y no puede modificarse ni eliminarse.'', 1;
        END
    END;');

    /* ------------------------------------------------------------------------
       ERROR 007 · Auditoría con identidad real de la aplicación.
       La aplicación envía el empleado con sp_set_session_context (ver
       app/Http/Middleware/IdentificarUsuarioSql.php). Si no lo envía, queda
       la cuenta de conexión como respaldo.
       ------------------------------------------------------------------------ */
    EXEC(N'
    CREATE OR ALTER TRIGGER dbo.trg_pedido_auditoria
    ON dbo.PEDIDO
    AFTER INSERT, UPDATE, DELETE
    AS
    BEGIN
        SET NOCOUNT ON;

        DECLARE @actor INT = TRY_CAST(SESSION_CONTEXT(N''id_empleado'') AS INT);
        DECLARE @usuario VARCHAR(100) = COALESCE(CAST(SESSION_CONTEXT(N''usuario_app'') AS VARCHAR(100)), SUSER_SNAME());

        INSERT INTO dbo.AUDITORIA_PEDIDO (id_pedido, accion, usuario_bd, id_empleado_app, estado_nuevo, subtotal_nuevo)
        SELECT i.id_pedido, ''INSERT'', @usuario, COALESCE(@actor, i.id_empleado), i.estado, i.subtotal
        FROM inserted i
        WHERE NOT EXISTS (SELECT 1 FROM deleted d WHERE d.id_pedido = i.id_pedido);

        INSERT INTO dbo.AUDITORIA_PEDIDO (id_pedido, accion, usuario_bd, id_empleado_app, estado_anterior, estado_nuevo, subtotal_anterior, subtotal_nuevo)
        SELECT i.id_pedido, ''UPDATE'', @usuario, COALESCE(@actor, i.id_empleado), d.estado, i.estado, d.subtotal, i.subtotal
        FROM inserted i
        INNER JOIN deleted d ON i.id_pedido = d.id_pedido;

        INSERT INTO dbo.AUDITORIA_PEDIDO (id_pedido, accion, usuario_bd, id_empleado_app, estado_anterior, subtotal_anterior)
        SELECT d.id_pedido, ''DELETE'', @usuario, COALESCE(@actor, d.id_empleado), d.estado, d.subtotal
        FROM deleted d
        WHERE NOT EXISTS (SELECT 1 FROM inserted i WHERE i.id_pedido = d.id_pedido);
    END;');

    -- La bitácora no puede alterarse ni borrarse, ni siquiera por el administrador
    IF DATABASE_PRINCIPAL_ID(N'rol_administrador') IS NOT NULL
        DENY UPDATE, DELETE ON dbo.AUDITORIA_PEDIDO TO rol_administrador;
    IF DATABASE_PRINCIPAL_ID(N'rol_mesero') IS NOT NULL
        DENY UPDATE, DELETE ON dbo.AUDITORIA_PEDIDO TO rol_mesero;
    IF DATABASE_PRINCIPAL_ID(N'rol_cocinero') IS NOT NULL
        DENY UPDATE, DELETE ON dbo.AUDITORIA_PEDIDO TO rol_cocinero;
    IF DATABASE_PRINCIPAL_ID(N'rol_cajero') IS NOT NULL
        DENY UPDATE, DELETE ON dbo.AUDITORIA_PEDIDO TO rol_cajero;

    /* ------------------------------------------------------------------------
       ERROR 019 · Movimientos de inventario trazables.
       Cada cambio de stock_actual queda registrado con su diferencia y responsable.
       ------------------------------------------------------------------------ */
    IF OBJECT_ID(N'dbo.MOVIMIENTO_INSUMO', N'U') IS NULL
    CREATE TABLE dbo.MOVIMIENTO_INSUMO (
        id_movimiento INT IDENTITY(1,1) PRIMARY KEY,
        id_insumo INT NOT NULL,
        fecha_hora DATETIME NOT NULL DEFAULT GETDATE(),
        stock_anterior DECIMAL(10,2) NOT NULL,
        stock_nuevo DECIMAL(10,2) NOT NULL,
        diferencia AS (stock_nuevo - stock_anterior) PERSISTED,
        usuario_bd VARCHAR(100) NOT NULL DEFAULT SUSER_SNAME(),
        id_empleado_app INT NULL,
        motivo VARCHAR(200) NULL,
        CONSTRAINT FK_MOVIMIENTO_INSUMO FOREIGN KEY (id_insumo) REFERENCES dbo.INSUMO(id_insumo)
    );

    EXEC(N'
    CREATE OR ALTER TRIGGER dbo.trg_insumo_movimiento
    ON dbo.INSUMO
    AFTER UPDATE
    AS
    BEGIN
        SET NOCOUNT ON;

        IF NOT UPDATE(stock_actual)
            RETURN;

        INSERT INTO dbo.MOVIMIENTO_INSUMO (id_insumo, stock_anterior, stock_nuevo, usuario_bd, id_empleado_app, motivo)
        SELECT i.id_insumo, d.stock_actual, i.stock_actual,
               COALESCE(CAST(SESSION_CONTEXT(N''usuario_app'') AS VARCHAR(100)), SUSER_SNAME()),
               TRY_CAST(SESSION_CONTEXT(N''id_empleado'') AS INT),
               CAST(SESSION_CONTEXT(N''motivo_inventario'') AS VARCHAR(200))
        FROM inserted i
        INNER JOIN deleted d ON d.id_insumo = i.id_insumo
        WHERE i.stock_actual <> d.stock_actual;
    END;');

    IF DATABASE_PRINCIPAL_ID(N'rol_administrador') IS NOT NULL
        GRANT SELECT ON dbo.MOVIMIENTO_INSUMO TO rol_administrador;
    IF DATABASE_PRINCIPAL_ID(N'rol_administrador') IS NOT NULL
        DENY UPDATE, DELETE ON dbo.MOVIMIENTO_INSUMO TO rol_administrador;

    /* ------------------------------------------------------------------------
       ERROR 021 · Imagen de producto (ruta relativa dentro de public/).
       ------------------------------------------------------------------------ */
    IF COL_LENGTH(N'dbo.PRODUCTO', N'imagen_ruta') IS NULL
        ALTER TABLE dbo.PRODUCTO ADD imagen_ruta NVARCHAR(255) NULL;

    /* ------------------------------------------------------------------------
       ERROR 017 · Permiso de respaldo para el rol administrador.
       ------------------------------------------------------------------------ */
    IF DATABASE_PRINCIPAL_ID(N'rol_administrador') IS NOT NULL
        GRANT BACKUP DATABASE TO rol_administrador;

    COMMIT TRANSACTION;
    PRINT 'Correcciones de auditoría aplicadas.';
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
GO
