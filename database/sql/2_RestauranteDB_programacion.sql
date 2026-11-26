USE RestauranteDB;
GO

SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO


-- =========================================
-- VISTA vw_menu_activo
-- =========================================

CREATE VIEW vw_menu_activo AS
SELECT
    p.id_producto,
    p.nombre_producto,
    p.descripcion,
    p.precio,
    p.foto,
    c.nombre_categoria,
    e.nombre_estacion
FROM dbo.PRODUCTO p
INNER JOIN dbo.CATEGORIA c ON p.id_categoria = c.id_categoria
INNER JOIN dbo.ESTACION e ON p.id_estacion = e.id_estacion
WHERE p.activo = 1
GO


-- =========================================
-- VISTA vw_mesas_estado
-- =========================================

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
FROM dbo.MESA m
LEFT JOIN dbo.PEDIDO p ON m.id_mesa = p.id_mesa AND p.estado IN ('ABIERTO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO')
GO


-- =========================================
-- VISTA vw_cola_cocina
-- =========================================

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
FROM dbo.DETALLE_PEDIDO dp
INNER JOIN dbo.PRODUCTO p ON dp.id_producto = p.id_producto
INNER JOIN dbo.ESTACION e ON p.id_estacion = e.id_estacion
INNER JOIN dbo.PEDIDO ped ON dp.id_pedido = ped.id_pedido
INNER JOIN dbo.MESA m ON ped.id_mesa = m.id_mesa
WHERE dp.estado_cocina IN ('PENDIENTE', 'PREPARANDO')
GO


-- =========================================
-- VISTA vw_factura_ticket
-- =========================================

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
FROM dbo.FACTURA f
INNER JOIN dbo.PEDIDO p ON f.id_pedido = p.id_pedido
INNER JOIN dbo.MESA m ON p.id_mesa = m.id_mesa
INNER JOIN dbo.EMPLEADO emp ON f.id_empleado = emp.id_empleado
GO


-- =========================================
-- TRIGGER trg_producto_baja_logica
-- =========================================

CREATE TRIGGER trg_producto_baja_logica
ON PRODUCTO
INSTEAD OF DELETE
AS
BEGIN
    SET NOCOUNT ON

    BEGIN TRY
        IF EXISTS (
            SELECT 1
            FROM dbo.DETALLE_PEDIDO dp
            INNER JOIN deleted d ON d.id_producto = dp.id_producto
            WHERE dp.estado_cocina IN ('PENDIENTE', 'PREPARANDO')
        )
            THROW 50016, 'No se puede dar de baja un producto que cocina todavía está preparando.', 1

        DELETE p
        FROM dbo.PRODUCTO p
        INNER JOIN deleted d ON d.id_producto = p.id_producto
        WHERE NOT EXISTS (SELECT 1 FROM dbo.DETALLE_PEDIDO dp WHERE dp.id_producto = p.id_producto)

        UPDATE p
        SET p.activo = 0
        FROM dbo.PRODUCTO p
        INNER JOIN deleted d ON d.id_producto = p.id_producto
        WHERE EXISTS (SELECT 1 FROM dbo.DETALLE_PEDIDO dp WHERE dp.id_producto = p.id_producto)
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- TRIGGER trg_factura_no_eliminar
-- =========================================

CREATE TRIGGER trg_factura_no_eliminar
ON FACTURA
INSTEAD OF DELETE
AS
BEGIN
    SET NOCOUNT ON

    BEGIN TRY
        IF EXISTS (SELECT 1 FROM deleted)
            THROW 50010, 'Las facturas no se eliminan. Anule la factura en su lugar.', 1
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- TRIGGER trg_pedido_auditoria
-- =========================================

CREATE TRIGGER trg_pedido_auditoria
ON PEDIDO
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON

    BEGIN TRY
        DECLARE @actor INT = TRY_CAST(SESSION_CONTEXT(N'id_empleado') AS INT)
        DECLARE @usuario VARCHAR(100) = COALESCE(CAST(SESSION_CONTEXT(N'usuario_app') AS VARCHAR(100)), SUSER_SNAME())

        INSERT INTO dbo.AUDITORIA_PEDIDO (id_pedido, accion, usuario_bd, id_empleado, estado_nuevo, subtotal_nuevo)
        SELECT i.id_pedido, 'INSERT', @usuario, COALESCE(@actor, i.id_empleado), i.estado, i.subtotal
        FROM inserted i
        WHERE NOT EXISTS (SELECT 1 FROM deleted d WHERE d.id_pedido = i.id_pedido)

        INSERT INTO dbo.AUDITORIA_PEDIDO (id_pedido, accion, usuario_bd, id_empleado, estado_anterior, estado_nuevo, subtotal_anterior, subtotal_nuevo)
        SELECT i.id_pedido, 'UPDATE', @usuario, COALESCE(@actor, i.id_empleado), d.estado, i.estado, d.subtotal, i.subtotal
        FROM inserted i
        INNER JOIN deleted d ON i.id_pedido = d.id_pedido

        INSERT INTO dbo.AUDITORIA_PEDIDO (id_pedido, accion, usuario_bd, id_empleado, estado_anterior, subtotal_anterior)
        SELECT d.id_pedido, 'DELETE', @usuario, COALESCE(@actor, d.id_empleado), d.estado, d.subtotal
        FROM deleted d
        WHERE NOT EXISTS (SELECT 1 FROM inserted i WHERE i.id_pedido = d.id_pedido)
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- TRIGGER trg_pedido_estado_final
-- =========================================

CREATE TRIGGER trg_pedido_estado_final
ON PEDIDO
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON

    IF NOT UPDATE(estado)
        RETURN;

    BEGIN TRY
        IF EXISTS (
            SELECT 1
            FROM inserted i
            INNER JOIN deleted d ON d.id_pedido = i.id_pedido
            WHERE d.estado = 'ANULADO' AND i.estado <> d.estado
        )
            THROW 50013, 'Un pedido anulado no puede cambiar de estado.', 1

        IF EXISTS (
            SELECT 1
            FROM inserted i
            INNER JOIN deleted d ON d.id_pedido = i.id_pedido
            WHERE d.estado = 'FACTURADO'
              AND i.estado <> 'FACTURADO'
              AND (i.estado <> 'LISTO'
                   OR EXISTS (SELECT 1 FROM dbo.FACTURA f WHERE f.id_pedido = i.id_pedido AND f.estado = 'COBRADA'))
        )
            THROW 50013, 'Un pedido facturado solo puede reabrirse si su factura fue anulada.', 1

        IF EXISTS (
            SELECT 1
            FROM inserted i
            INNER JOIN deleted d ON d.id_pedido = i.id_pedido
            WHERE i.estado = 'FACTURADO'
              AND d.estado <> 'FACTURADO'
              AND NOT EXISTS (SELECT 1 FROM dbo.FACTURA f WHERE f.id_pedido = i.id_pedido AND f.estado = 'COBRADA')
        )
            THROW 50014, 'No se puede marcar un pedido como facturado sin una factura cobrada.', 1
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- TRIGGER trg_insumo_stock
-- =========================================

CREATE TRIGGER trg_insumo_stock
ON INSUMO
AFTER INSERT, UPDATE
AS
BEGIN
    SET NOCOUNT ON

    IF NOT (UPDATE(stock_actual) OR UPDATE(stock_minimo))
        RETURN;

    BEGIN TRY
        INSERT INTO dbo.ALERTA_STOCK (id_insumo, stock_actual, stock_minimo)
        SELECT i.id_insumo, i.stock_actual, i.stock_minimo
        FROM inserted i
        WHERE i.stock_actual <= i.stock_minimo
          AND NOT EXISTS (SELECT 1 FROM dbo.ALERTA_STOCK a WHERE a.id_insumo = i.id_insumo AND a.atendida = 0)

        UPDATE a
        SET a.atendida = 1
        FROM dbo.ALERTA_STOCK a
        INNER JOIN inserted i ON i.id_insumo = a.id_insumo
        WHERE a.atendida = 0
          AND i.stock_actual > i.stock_minimo
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- TRIGGER trg_factura_inmutable
-- =========================================

CREATE TRIGGER trg_factura_inmutable
ON FACTURA
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON

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
            THROW 50011, 'Los datos de una factura emitida no se pueden modificar. Solo se permite anularla.', 1
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- TRIGGER trg_pago_inmutable
-- =========================================

CREATE TRIGGER trg_pago_inmutable
ON PAGO
AFTER UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON

    BEGIN TRY
        IF EXISTS (SELECT 1 FROM deleted)
            THROW 50012, 'Los pagos registrados no se pueden modificar ni eliminar.', 1
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- TRIGGER trg_turno_caja_cerrado
-- =========================================

CREATE TRIGGER trg_turno_caja_cerrado
ON TURNO_CAJA
AFTER UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON

    BEGIN TRY
        IF EXISTS (SELECT 1 FROM deleted d WHERE d.fecha_cierre IS NOT NULL)
            THROW 50015, 'El turno ya está cerrado y no puede modificarse ni eliminarse.', 1
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- FUNCION fn_tiene_permiso
-- =========================================

CREATE FUNCTION fn_tiene_permiso (@id_empleado INT, @clave VARCHAR(60))
RETURNS BIT
AS
BEGIN
    RETURN CASE WHEN EXISTS (
        SELECT 1
        FROM dbo.EMPLEADO e
        INNER JOIN dbo.ROL_PERMISO rp ON rp.id_rol = e.id_rol
        INNER JOIN dbo.PERMISO p ON p.id_permiso = rp.id_permiso
        WHERE e.id_empleado = @id_empleado AND e.activo = 1 AND p.clave = @clave
    ) THEN 1 ELSE 0 END
END
GO


-- =========================================
-- VISTA vw_cliente
-- Devuelve la cédula descifrada. DECRYPTBYKEYAUTOCERT abre la llave con el
-- certificado: quien no tenga permiso sobre el certificado recibe NULL.
-- =========================================

CREATE VIEW vw_cliente AS
SELECT
    c.id_cliente,
    CONVERT(VARCHAR(20), DECRYPTBYKEYAUTOCERT(CERT_ID('cert_datos_personales'), NULL, c.cedula)) AS cedula,
    c.nombre,
    c.apellido,
    c.telefono,
    c.correo
FROM dbo.CLIENTE c
GO

-- =========================================
-- VISTA vw_permisos_empleado
-- =========================================

CREATE VIEW vw_permisos_empleado AS
SELECT e.id_empleado, p.clave
FROM dbo.EMPLEADO e
INNER JOIN dbo.ROL_PERMISO rp ON rp.id_rol = e.id_rol
INNER JOIN dbo.PERMISO p ON p.id_permiso = rp.id_permiso
WHERE e.activo = 1
GO


-- =========================================
-- PROCEDIMIENTO sp_rol_reemplazar_permisos
-- =========================================

CREATE PROCEDURE sp_rol_reemplazar_permisos
    @id_rol INT,
    @permisos NVARCHAR(MAX)
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    DECLARE @claves TABLE (clave VARCHAR(60) PRIMARY KEY)
    DECLARE @desconocido VARCHAR(60), @mensaje NVARCHAR(200)
    -- Se llama desde sp_rol_insertar / sp_rol_actualizar, que ya abrieron una transacción.
    -- Solo abre (y confirma o revierte) la suya cuando se ejecuta solo.
    DECLARE @transaccion_propia BIT = 0

    BEGIN TRY
        IF ISJSON(ISNULL(@permisos, N'')) = 0
            THROW 50104, 'La lista de permisos no tiene un formato válido.', 1

        INSERT INTO @claves (clave)
        SELECT DISTINCT LTRIM(RTRIM(value)) FROM OPENJSON(@permisos)

        SELECT TOP (1) @desconocido = c.clave
        FROM @claves c
        WHERE NOT EXISTS (SELECT 1 FROM dbo.PERMISO p WHERE p.clave = c.clave)

        IF @desconocido IS NOT NULL
        BEGIN
            SET @mensaje = CONCAT(N'El permiso ', @desconocido, N' no existe.');
            THROW 50104, @mensaje, 1
        END

        IF @@TRANCOUNT = 0
        BEGIN
            BEGIN TRANSACTION;
            SET @transaccion_propia = 1
        END

        DELETE FROM dbo.ROL_PERMISO WHERE id_rol = @id_rol

        INSERT INTO dbo.ROL_PERMISO (id_rol, id_permiso)
        SELECT @id_rol, p.id_permiso
        FROM dbo.PERMISO p
        INNER JOIN @claves c ON c.clave = p.clave

        IF NOT EXISTS (SELECT 1 FROM dbo.EMPLEADO e WHERE dbo.fn_tiene_permiso(e.id_empleado, 'roles.editar') = 1)
            THROW 50105, 'Debe quedar al menos un empleado activo con permiso para editar roles.', 1

        IF @transaccion_propia = 1
            COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @transaccion_propia = 1 AND @@TRANCOUNT > 0
            ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_rol_insertar
-- =========================================

CREATE PROCEDURE sp_rol_insertar
    @nombre_rol VARCHAR(50),
    @permisos NVARCHAR(MAX) = N'[]',
    @id_rol INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NULLIF(LTRIM(RTRIM(@nombre_rol)), '') IS NULL
            THROW 50100, 'El nombre del rol es obligatorio.', 1

        BEGIN TRANSACTION;

        INSERT INTO dbo.ROL (nombre_rol)
        VALUES (LTRIM(RTRIM(@nombre_rol)))

        SET @id_rol = SCOPE_IDENTITY()

        EXEC dbo.sp_rol_reemplazar_permisos @id_rol = @id_rol, @permisos = @permisos

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un rol con ese nombre.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_rol_actualizar
-- =========================================

CREATE PROCEDURE sp_rol_actualizar
    @id_rol INT,
    @nombre_rol VARCHAR(50),
    @permisos NVARCHAR(MAX) = NULL
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.ROL WHERE id_rol = @id_rol)
            THROW 50101, 'El rol no existe.', 1
        IF NULLIF(LTRIM(RTRIM(@nombre_rol)), '') IS NULL
            THROW 50100, 'El nombre del rol es obligatorio.', 1

        BEGIN TRANSACTION;

        UPDATE dbo.ROL
        SET nombre_rol = LTRIM(RTRIM(@nombre_rol))
        WHERE id_rol = @id_rol

        IF @permisos IS NOT NULL
            EXEC dbo.sp_rol_reemplazar_permisos @id_rol = @id_rol, @permisos = @permisos

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un rol con ese nombre.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_rol_eliminar
-- =========================================

CREATE PROCEDURE sp_rol_eliminar
    @id_rol INT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.ROL WHERE id_rol = @id_rol)
            THROW 50101, 'El rol no existe.', 1

        BEGIN TRANSACTION;

        DELETE FROM dbo.ROL WHERE id_rol = @id_rol

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar el rol: tiene empleados asociados.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_rol_consultar
-- =========================================

CREATE PROCEDURE sp_rol_consultar
    @id_rol INT = NULL
AS
BEGIN
    SET NOCOUNT ON

    BEGIN TRY
        IF @id_rol IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.ROL WHERE id_rol = @id_rol)
            THROW 50101, 'El rol no existe.', 1

        SELECT r.id_rol, r.nombre_rol, p.clave AS permiso
        FROM dbo.ROL r
        LEFT JOIN dbo.ROL_PERMISO rp ON rp.id_rol = r.id_rol
        LEFT JOIN dbo.PERMISO p ON p.id_permiso = rp.id_permiso
        WHERE @id_rol IS NULL OR r.id_rol = @id_rol
        ORDER BY r.nombre_rol, p.clave
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_estacion_insertar
-- =========================================

CREATE PROCEDURE sp_estacion_insertar
    @nombre_estacion VARCHAR(50),
    @id_estacion INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NULLIF(LTRIM(RTRIM(@nombre_estacion)), '') IS NULL
            THROW 50100, 'El nombre de la estación es obligatorio.', 1

        BEGIN TRANSACTION;

        INSERT INTO dbo.ESTACION (nombre_estacion)
        VALUES (LTRIM(RTRIM(@nombre_estacion)))

        SET @id_estacion = SCOPE_IDENTITY()

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe una estación con ese nombre.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos de la estación no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_estacion_actualizar
-- =========================================

CREATE PROCEDURE sp_estacion_actualizar
    @id_estacion INT,
    @nombre_estacion VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.ESTACION WHERE id_estacion = @id_estacion)
            THROW 50101, 'La estación no existe.', 1
        IF NULLIF(LTRIM(RTRIM(@nombre_estacion)), '') IS NULL
            THROW 50100, 'El nombre de la estación es obligatorio.', 1

        BEGIN TRANSACTION;

        UPDATE dbo.ESTACION
        SET nombre_estacion = LTRIM(RTRIM(@nombre_estacion))
        WHERE id_estacion = @id_estacion

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe una estación con ese nombre.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos de la estación no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_estacion_eliminar
-- =========================================

CREATE PROCEDURE sp_estacion_eliminar
    @id_estacion INT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.ESTACION WHERE id_estacion = @id_estacion)
            THROW 50101, 'La estación no existe.', 1

        BEGIN TRANSACTION;

        DELETE FROM dbo.ESTACION WHERE id_estacion = @id_estacion

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar la estación: tiene productos o cocineros asociados.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_estacion_consultar
-- =========================================

CREATE PROCEDURE sp_estacion_consultar
    @id_estacion INT = NULL
AS
BEGIN
    SET NOCOUNT ON

    BEGIN TRY
        IF @id_estacion IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.ESTACION WHERE id_estacion = @id_estacion)
            THROW 50101, 'La estación no existe.', 1

        SELECT id_estacion, nombre_estacion
        FROM dbo.ESTACION
        WHERE @id_estacion IS NULL OR id_estacion = @id_estacion
        ORDER BY nombre_estacion
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_categoria_insertar
-- =========================================

CREATE PROCEDURE sp_categoria_insertar
    @nombre_categoria VARCHAR(50),
    @descripcion VARCHAR(200) = NULL,
    @id_categoria INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NULLIF(LTRIM(RTRIM(@nombre_categoria)), '') IS NULL
            THROW 50100, 'El nombre de la categoría es obligatorio.', 1

        BEGIN TRANSACTION;

        INSERT INTO dbo.CATEGORIA (nombre_categoria, descripcion)
        VALUES (LTRIM(RTRIM(@nombre_categoria)), NULLIF(LTRIM(RTRIM(@descripcion)), ''))

        SET @id_categoria = SCOPE_IDENTITY()

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe una categoría con ese nombre.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos de la categoría no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_categoria_actualizar
-- =========================================

CREATE PROCEDURE sp_categoria_actualizar
    @id_categoria INT,
    @nombre_categoria VARCHAR(50),
    @descripcion VARCHAR(200) = NULL
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.CATEGORIA WHERE id_categoria = @id_categoria)
            THROW 50101, 'La categoría no existe.', 1
        IF NULLIF(LTRIM(RTRIM(@nombre_categoria)), '') IS NULL
            THROW 50100, 'El nombre de la categoría es obligatorio.', 1

        BEGIN TRANSACTION;

        UPDATE dbo.CATEGORIA
        SET nombre_categoria = LTRIM(RTRIM(@nombre_categoria)),
            descripcion = NULLIF(LTRIM(RTRIM(@descripcion)), '')
        WHERE id_categoria = @id_categoria

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe una categoría con ese nombre.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos de la categoría no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_categoria_eliminar
-- =========================================

CREATE PROCEDURE sp_categoria_eliminar
    @id_categoria INT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.CATEGORIA WHERE id_categoria = @id_categoria)
            THROW 50101, 'La categoría no existe.', 1

        BEGIN TRANSACTION;

        DELETE FROM dbo.CATEGORIA WHERE id_categoria = @id_categoria

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar la categoría: tiene productos asociados.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_categoria_consultar
-- =========================================

CREATE PROCEDURE sp_categoria_consultar
    @id_categoria INT = NULL
AS
BEGIN
    SET NOCOUNT ON

    BEGIN TRY
        IF @id_categoria IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.CATEGORIA WHERE id_categoria = @id_categoria)
            THROW 50101, 'La categoría no existe.', 1

        SELECT id_categoria, nombre_categoria, descripcion
        FROM dbo.CATEGORIA
        WHERE @id_categoria IS NULL OR id_categoria = @id_categoria
        ORDER BY nombre_categoria
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_metodo_pago_insertar
-- =========================================

CREATE PROCEDURE sp_metodo_pago_insertar
    @nombre_metodo VARCHAR(50),
    @id_metodo_pago INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NULLIF(LTRIM(RTRIM(@nombre_metodo)), '') IS NULL
            THROW 50100, 'El nombre del método de pago es obligatorio.', 1

        BEGIN TRANSACTION;

        INSERT INTO dbo.METODO_PAGO (nombre_metodo)
        VALUES (LTRIM(RTRIM(@nombre_metodo)))

        SET @id_metodo_pago = SCOPE_IDENTITY()

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un método de pago con ese nombre.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del método de pago no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_metodo_pago_actualizar
-- =========================================

CREATE PROCEDURE sp_metodo_pago_actualizar
    @id_metodo_pago INT,
    @nombre_metodo VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.METODO_PAGO WHERE id_metodo_pago = @id_metodo_pago)
            THROW 50101, 'El método de pago no existe.', 1
        IF NULLIF(LTRIM(RTRIM(@nombre_metodo)), '') IS NULL
            THROW 50100, 'El nombre del método de pago es obligatorio.', 1

        BEGIN TRANSACTION;

        UPDATE dbo.METODO_PAGO
        SET nombre_metodo = LTRIM(RTRIM(@nombre_metodo))
        WHERE id_metodo_pago = @id_metodo_pago

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un método de pago con ese nombre.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del método de pago no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_metodo_pago_eliminar
-- =========================================

CREATE PROCEDURE sp_metodo_pago_eliminar
    @id_metodo_pago INT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.METODO_PAGO WHERE id_metodo_pago = @id_metodo_pago)
            THROW 50101, 'El método de pago no existe.', 1

        BEGIN TRANSACTION;

        DELETE FROM dbo.METODO_PAGO WHERE id_metodo_pago = @id_metodo_pago

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar el método de pago: tiene pagos asociados.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_metodo_pago_consultar
-- =========================================

CREATE PROCEDURE sp_metodo_pago_consultar
    @id_metodo_pago INT = NULL
AS
BEGIN
    SET NOCOUNT ON

    BEGIN TRY
        IF @id_metodo_pago IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.METODO_PAGO WHERE id_metodo_pago = @id_metodo_pago)
            THROW 50101, 'El método de pago no existe.', 1

        SELECT id_metodo_pago, nombre_metodo
        FROM dbo.METODO_PAGO
        WHERE @id_metodo_pago IS NULL OR id_metodo_pago = @id_metodo_pago
        ORDER BY nombre_metodo
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_mesa_insertar
-- =========================================

CREATE PROCEDURE sp_mesa_insertar
    @numero_mesa INT,
    @capacidad INT,
    @zona VARCHAR(50),
    @estado VARCHAR(20) = 'DISPONIBLE',
    @id_mesa INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF @numero_mesa IS NULL OR @numero_mesa < 1
            THROW 50100, 'El número de mesa debe ser mayor que cero.', 1
        IF @capacidad IS NULL OR @capacidad < 1
            THROW 50100, 'La capacidad debe ser mayor que cero.', 1
        IF NULLIF(LTRIM(RTRIM(@zona)), '') IS NULL
            THROW 50100, 'La zona de la mesa es obligatoria.', 1
        IF @estado NOT IN ('DISPONIBLE', 'OCUPADA', 'RESERVADA', 'MANTENIMIENTO')
            THROW 50100, 'Estado de mesa no válido.', 1

        BEGIN TRANSACTION;

        INSERT INTO dbo.MESA (numero_mesa, capacidad, zona, estado)
        VALUES (@numero_mesa, @capacidad, LTRIM(RTRIM(@zona)), LTRIM(RTRIM(@estado)))

        SET @id_mesa = SCOPE_IDENTITY()

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe una mesa con ese número.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos de la mesa no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_mesa_actualizar
-- =========================================

CREATE PROCEDURE sp_mesa_actualizar
    @id_mesa INT,
    @numero_mesa INT,
    @capacidad INT,
    @zona VARCHAR(50),
    @estado VARCHAR(20) = 'DISPONIBLE'
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.MESA WHERE id_mesa = @id_mesa)
            THROW 50101, 'La mesa no existe.', 1
        IF @numero_mesa IS NULL OR @numero_mesa < 1
            THROW 50100, 'El número de mesa debe ser mayor que cero.', 1
        IF @capacidad IS NULL OR @capacidad < 1
            THROW 50100, 'La capacidad debe ser mayor que cero.', 1
        IF NULLIF(LTRIM(RTRIM(@zona)), '') IS NULL
            THROW 50100, 'La zona de la mesa es obligatoria.', 1
        IF @estado NOT IN ('DISPONIBLE', 'OCUPADA', 'RESERVADA', 'MANTENIMIENTO')
            THROW 50100, 'Estado de mesa no válido.', 1

        BEGIN TRANSACTION;

        UPDATE dbo.MESA
        SET numero_mesa = @numero_mesa,
            capacidad = @capacidad,
            zona = LTRIM(RTRIM(@zona)),
            estado = LTRIM(RTRIM(@estado))
        WHERE id_mesa = @id_mesa

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe una mesa con ese número.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos de la mesa no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_mesa_eliminar
-- =========================================

CREATE PROCEDURE sp_mesa_eliminar
    @id_mesa INT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.MESA WHERE id_mesa = @id_mesa)
            THROW 50101, 'La mesa no existe.', 1

        BEGIN TRANSACTION;

        DELETE FROM dbo.MESA WHERE id_mesa = @id_mesa

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar la mesa: tiene pedidos asociados. Puede ponerla en mantenimiento.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_mesa_consultar
-- =========================================

CREATE PROCEDURE sp_mesa_consultar
    @id_mesa INT = NULL
AS
BEGIN
    SET NOCOUNT ON

    BEGIN TRY
        IF @id_mesa IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.MESA WHERE id_mesa = @id_mesa)
            THROW 50101, 'La mesa no existe.', 1

        SELECT id_mesa, numero_mesa, capacidad, zona, estado
        FROM dbo.MESA
        WHERE @id_mesa IS NULL OR id_mesa = @id_mesa
        ORDER BY numero_mesa
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_cliente_insertar
-- =========================================

CREATE PROCEDURE sp_cliente_insertar
    @cedula VARCHAR(20),
    @nombre VARCHAR(50),
    @apellido VARCHAR(50),
    @telefono VARCHAR(20) = NULL,
    @correo VARCHAR(100) = NULL,
    @id_cliente INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        -- La cédula se normaliza (sin guiones ni espacios) antes de cifrarla y de calcular su huella
        SET @cedula = REPLACE(REPLACE(LTRIM(RTRIM(@cedula)), '-', ''), ' ', '')

        IF NULLIF(@cedula, '') IS NULL
            THROW 50100, 'La cédula del cliente es obligatoria.', 1
        IF @cedula LIKE '%[^0-9A-Za-z]%'
            THROW 50100, 'La cédula solo puede tener números y letras.', 1
        IF NULLIF(LTRIM(RTRIM(@nombre)), '') IS NULL OR NULLIF(LTRIM(RTRIM(@apellido)), '') IS NULL
            THROW 50100, 'Nombre y apellido del cliente son obligatorios.', 1

        BEGIN TRANSACTION;

        -- Cifrado AES-256 con la llave simétrica protegida por certificado (ver 1_RestauranteDB_estructura.sql)
        OPEN SYMMETRIC KEY llave_datos_personales DECRYPTION BY CERTIFICATE cert_datos_personales;

        INSERT INTO dbo.CLIENTE (cedula, cedula_hash, nombre, apellido, telefono, correo)
        VALUES (
            ENCRYPTBYKEY(KEY_GUID('llave_datos_personales'), @cedula),
            HASHBYTES('SHA2_256', @cedula),
            LTRIM(RTRIM(@nombre)),
            LTRIM(RTRIM(@apellido)),
            NULLIF(LTRIM(RTRIM(@telefono)), ''),
            NULLIF(LTRIM(RTRIM(@correo)), '')
        )

        SET @id_cliente = SCOPE_IDENTITY()

        CLOSE SYMMETRIC KEY llave_datos_personales;

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF EXISTS (SELECT 1 FROM sys.openkeys WHERE key_name = 'llave_datos_personales')
            CLOSE SYMMETRIC KEY llave_datos_personales;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un cliente con esa cédula.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del cliente no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_cliente_actualizar
-- =========================================

CREATE PROCEDURE sp_cliente_actualizar
    @id_cliente INT,
    @cedula VARCHAR(20),
    @nombre VARCHAR(50),
    @apellido VARCHAR(50),
    @telefono VARCHAR(20) = NULL,
    @correo VARCHAR(100) = NULL
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.CLIENTE WHERE id_cliente = @id_cliente)
            THROW 50101, 'El cliente no existe.', 1
        SET @cedula = REPLACE(REPLACE(LTRIM(RTRIM(@cedula)), '-', ''), ' ', '')

        IF NULLIF(@cedula, '') IS NULL
            THROW 50100, 'La cédula del cliente es obligatoria.', 1
        IF @cedula LIKE '%[^0-9A-Za-z]%'
            THROW 50100, 'La cédula solo puede tener números y letras.', 1
        IF NULLIF(LTRIM(RTRIM(@nombre)), '') IS NULL OR NULLIF(LTRIM(RTRIM(@apellido)), '') IS NULL
            THROW 50100, 'Nombre y apellido del cliente son obligatorios.', 1

        BEGIN TRANSACTION;

        OPEN SYMMETRIC KEY llave_datos_personales DECRYPTION BY CERTIFICATE cert_datos_personales;

        UPDATE dbo.CLIENTE
        SET cedula = ENCRYPTBYKEY(KEY_GUID('llave_datos_personales'), @cedula),
            cedula_hash = HASHBYTES('SHA2_256', @cedula),
            nombre = LTRIM(RTRIM(@nombre)),
            apellido = LTRIM(RTRIM(@apellido)),
            telefono = NULLIF(LTRIM(RTRIM(@telefono)), ''),
            correo = NULLIF(LTRIM(RTRIM(@correo)), '')
        WHERE id_cliente = @id_cliente

        CLOSE SYMMETRIC KEY llave_datos_personales;

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF EXISTS (SELECT 1 FROM sys.openkeys WHERE key_name = 'llave_datos_personales')
            CLOSE SYMMETRIC KEY llave_datos_personales;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un cliente con esa cédula.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del cliente no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_cliente_eliminar
-- =========================================

CREATE PROCEDURE sp_cliente_eliminar
    @id_cliente INT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.CLIENTE WHERE id_cliente = @id_cliente)
            THROW 50101, 'El cliente no existe.', 1

        BEGIN TRANSACTION;

        DELETE FROM dbo.CLIENTE WHERE id_cliente = @id_cliente

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar el cliente: tiene facturas asociadas.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_cliente_consultar
-- =========================================

CREATE PROCEDURE sp_cliente_consultar
    @id_cliente INT = NULL
AS
BEGIN
    SET NOCOUNT ON

    BEGIN TRY
        IF @id_cliente IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.CLIENTE WHERE id_cliente = @id_cliente)
            THROW 50101, 'El cliente no existe.', 1

        -- La cédula se devuelve descifrada; solo quien tenga permiso sobre el certificado puede leerla
        SELECT id_cliente, cedula, nombre, apellido, telefono, correo
        FROM dbo.vw_cliente
        WHERE @id_cliente IS NULL OR id_cliente = @id_cliente
        ORDER BY apellido, nombre
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_producto_insertar
-- =========================================

CREATE PROCEDURE sp_producto_insertar
    @nombre_producto VARCHAR(100),
    @descripcion VARCHAR(255) = NULL,
    @precio DECIMAL(10,2),
    @id_categoria INT,
    @id_estacion INT,
    @activo BIT = 1,
    @foto VARCHAR(255) = NULL,
    @id_producto INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NULLIF(LTRIM(RTRIM(@nombre_producto)), '') IS NULL
            THROW 50100, 'El nombre del producto es obligatorio.', 1
        IF @precio IS NULL OR @precio < 0
            THROW 50100, 'El precio no puede ser negativo.', 1
        IF NOT EXISTS (SELECT 1 FROM dbo.CATEGORIA WHERE id_categoria = @id_categoria)
            THROW 50100, 'La categoría indicada no existe.', 1
        IF NOT EXISTS (SELECT 1 FROM dbo.ESTACION WHERE id_estacion = @id_estacion)
            THROW 50100, 'La estación indicada no existe.', 1

        BEGIN TRANSACTION;

        INSERT INTO dbo.PRODUCTO (nombre_producto, descripcion, precio, id_categoria, id_estacion, activo, foto)
        VALUES (LTRIM(RTRIM(@nombre_producto)), NULLIF(LTRIM(RTRIM(@descripcion)), ''), @precio, @id_categoria, @id_estacion, @activo, NULLIF(LTRIM(RTRIM(@foto)), ''))

        SET @id_producto = SCOPE_IDENTITY()

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un producto con ese nombre.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del producto no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_producto_actualizar
-- =========================================

CREATE PROCEDURE sp_producto_actualizar
    @id_producto INT,
    @nombre_producto VARCHAR(100),
    @descripcion VARCHAR(255) = NULL,
    @precio DECIMAL(10,2),
    @id_categoria INT,
    @id_estacion INT,
    @activo BIT = 1,
    @foto VARCHAR(255) = NULL
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.PRODUCTO WHERE id_producto = @id_producto)
            THROW 50101, 'El producto no existe.', 1
        IF NULLIF(LTRIM(RTRIM(@nombre_producto)), '') IS NULL
            THROW 50100, 'El nombre del producto es obligatorio.', 1
        IF @precio IS NULL OR @precio < 0
            THROW 50100, 'El precio no puede ser negativo.', 1
        IF NOT EXISTS (SELECT 1 FROM dbo.CATEGORIA WHERE id_categoria = @id_categoria)
            THROW 50100, 'La categoría indicada no existe.', 1
        IF NOT EXISTS (SELECT 1 FROM dbo.ESTACION WHERE id_estacion = @id_estacion)
            THROW 50100, 'La estación indicada no existe.', 1

        BEGIN TRANSACTION;

        UPDATE dbo.PRODUCTO
        SET nombre_producto = LTRIM(RTRIM(@nombre_producto)),
            descripcion = NULLIF(LTRIM(RTRIM(@descripcion)), ''),
            precio = @precio,
            id_categoria = @id_categoria,
            id_estacion = @id_estacion,
            activo = @activo,
            foto = NULLIF(LTRIM(RTRIM(@foto)), '')
        WHERE id_producto = @id_producto

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un producto con ese nombre.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del producto no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_producto_eliminar
-- =========================================

CREATE PROCEDURE sp_producto_eliminar
    @id_producto INT,
    @desactivado BIT OUTPUT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.PRODUCTO WHERE id_producto = @id_producto)
            THROW 50101, 'El producto no existe.', 1

        BEGIN TRANSACTION;

        DELETE FROM dbo.PRODUCTO WHERE id_producto = @id_producto

        SET @desactivado = CASE WHEN EXISTS (SELECT 1 FROM dbo.PRODUCTO WHERE id_producto = @id_producto) THEN 1 ELSE 0 END

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar el producto: tiene registros relacionados.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_producto_consultar
-- =========================================

CREATE PROCEDURE sp_producto_consultar
    @id_producto INT = NULL
AS
BEGIN
    SET NOCOUNT ON

    BEGIN TRY
        IF @id_producto IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.PRODUCTO WHERE id_producto = @id_producto)
            THROW 50101, 'El producto no existe.', 1

        SELECT id_producto, nombre_producto, descripcion, precio, id_categoria, id_estacion, activo, foto
        FROM dbo.PRODUCTO
        WHERE @id_producto IS NULL OR id_producto = @id_producto
        ORDER BY nombre_producto
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_insumo_insertar
-- =========================================

CREATE PROCEDURE sp_insumo_insertar
    @nombre_insumo VARCHAR(100),
    @unidad_medida VARCHAR(20),
    @stock_actual DECIMAL(10,2),
    @stock_minimo DECIMAL(10,2),
    @costo_unitario DECIMAL(10,2),
    @id_insumo INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NULLIF(LTRIM(RTRIM(@nombre_insumo)), '') IS NULL OR NULLIF(LTRIM(RTRIM(@unidad_medida)), '') IS NULL
            THROW 50100, 'Nombre y unidad de medida del insumo son obligatorios.', 1
        IF @stock_actual IS NULL OR @stock_actual < 0 OR @stock_minimo IS NULL OR @stock_minimo < 0 OR @costo_unitario IS NULL OR @costo_unitario < 0
            THROW 50100, 'Stock y costo no pueden ser negativos.', 1

        BEGIN TRANSACTION;

        INSERT INTO dbo.INSUMO (nombre_insumo, unidad_medida, stock_actual, stock_minimo, costo_unitario)
        VALUES (LTRIM(RTRIM(@nombre_insumo)), LTRIM(RTRIM(@unidad_medida)), @stock_actual, @stock_minimo, @costo_unitario)

        SET @id_insumo = SCOPE_IDENTITY()

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un insumo con ese nombre.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del insumo no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_insumo_actualizar
-- =========================================

CREATE PROCEDURE sp_insumo_actualizar
    @id_insumo INT,
    @nombre_insumo VARCHAR(100),
    @unidad_medida VARCHAR(20),
    @stock_actual DECIMAL(10,2),
    @stock_minimo DECIMAL(10,2),
    @costo_unitario DECIMAL(10,2)
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.INSUMO WHERE id_insumo = @id_insumo)
            THROW 50101, 'El insumo no existe.', 1
        IF NULLIF(LTRIM(RTRIM(@nombre_insumo)), '') IS NULL OR NULLIF(LTRIM(RTRIM(@unidad_medida)), '') IS NULL
            THROW 50100, 'Nombre y unidad de medida del insumo son obligatorios.', 1
        IF @stock_actual IS NULL OR @stock_actual < 0 OR @stock_minimo IS NULL OR @stock_minimo < 0 OR @costo_unitario IS NULL OR @costo_unitario < 0
            THROW 50100, 'Stock y costo no pueden ser negativos.', 1

        BEGIN TRANSACTION;

        UPDATE dbo.INSUMO
        SET nombre_insumo = LTRIM(RTRIM(@nombre_insumo)),
            unidad_medida = LTRIM(RTRIM(@unidad_medida)),
            stock_actual = @stock_actual,
            stock_minimo = @stock_minimo,
            costo_unitario = @costo_unitario
        WHERE id_insumo = @id_insumo

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un insumo con ese nombre.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del insumo no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_insumo_eliminar
-- =========================================

CREATE PROCEDURE sp_insumo_eliminar
    @id_insumo INT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.INSUMO WHERE id_insumo = @id_insumo)
            THROW 50101, 'El insumo no existe.', 1

        BEGIN TRANSACTION;

        DELETE FROM dbo.INSUMO WHERE id_insumo = @id_insumo

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar el insumo: se usa en recetas o tiene alertas de stock.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_insumo_consultar
-- =========================================

CREATE PROCEDURE sp_insumo_consultar
    @id_insumo INT = NULL
AS
BEGIN
    SET NOCOUNT ON

    BEGIN TRY
        IF @id_insumo IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.INSUMO WHERE id_insumo = @id_insumo)
            THROW 50101, 'El insumo no existe.', 1

        SELECT id_insumo, nombre_insumo, unidad_medida, stock_actual, stock_minimo, costo_unitario
        FROM dbo.INSUMO
        WHERE @id_insumo IS NULL OR id_insumo = @id_insumo
        ORDER BY nombre_insumo
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_receta_insertar
-- =========================================

CREATE PROCEDURE sp_receta_insertar
    @id_producto INT,
    @id_insumo INT,
    @cantidad DECIMAL(10,2)
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF @cantidad IS NULL OR @cantidad <= 0
            THROW 50100, 'La cantidad de la receta debe ser mayor que cero.', 1
        IF NOT EXISTS (SELECT 1 FROM dbo.PRODUCTO WHERE id_producto = @id_producto)
            THROW 50100, 'El producto indicado no existe.', 1
        IF NOT EXISTS (SELECT 1 FROM dbo.INSUMO WHERE id_insumo = @id_insumo)
            THROW 50100, 'El insumo indicado no existe.', 1

        BEGIN TRANSACTION;

        INSERT INTO dbo.RECETA (id_producto, id_insumo, cantidad)
        VALUES (@id_producto, @id_insumo, @cantidad)

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ese insumo ya está en la receta del producto.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos de la receta no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_receta_actualizar
-- =========================================

CREATE PROCEDURE sp_receta_actualizar
    @id_producto INT,
    @id_insumo INT,
    @cantidad DECIMAL(10,2)
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.RECETA WHERE id_producto = @id_producto AND id_insumo = @id_insumo)
            THROW 50101, 'La línea de receta no existe.', 1
        IF @cantidad IS NULL OR @cantidad <= 0
            THROW 50100, 'La cantidad de la receta debe ser mayor que cero.', 1
        IF NOT EXISTS (SELECT 1 FROM dbo.PRODUCTO WHERE id_producto = @id_producto)
            THROW 50100, 'El producto indicado no existe.', 1
        IF NOT EXISTS (SELECT 1 FROM dbo.INSUMO WHERE id_insumo = @id_insumo)
            THROW 50100, 'El insumo indicado no existe.', 1

        BEGIN TRANSACTION;

        UPDATE dbo.RECETA
        SET cantidad = @cantidad
        WHERE id_producto = @id_producto AND id_insumo = @id_insumo

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ese insumo ya está en la receta del producto.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos de la receta no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_receta_eliminar
-- =========================================

CREATE PROCEDURE sp_receta_eliminar
    @id_producto INT,
    @id_insumo INT = NULL
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF @id_insumo IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.RECETA WHERE id_producto = @id_producto AND id_insumo = @id_insumo)
            THROW 50101, 'La línea de receta indicada no existe.', 1
        IF NOT EXISTS (SELECT 1 FROM dbo.PRODUCTO WHERE id_producto = @id_producto)
            THROW 50101, 'El producto indicado no existe.', 1

        BEGIN TRANSACTION;

        DELETE FROM dbo.RECETA
        WHERE id_producto = @id_producto AND (@id_insumo IS NULL OR id_insumo = @id_insumo)

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar la receta.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_receta_consultar
-- =========================================

CREATE PROCEDURE sp_receta_consultar
    @id_producto INT = NULL
AS
BEGIN
    SET NOCOUNT ON

    BEGIN TRY
        IF @id_producto IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.PRODUCTO WHERE id_producto = @id_producto)
            THROW 50101, 'El producto indicado no existe.', 1

        SELECT r.id_producto, p.nombre_producto, r.id_insumo, i.nombre_insumo, i.unidad_medida, r.cantidad
        FROM dbo.RECETA r
        INNER JOIN dbo.PRODUCTO p ON p.id_producto = r.id_producto
        INNER JOIN dbo.INSUMO i ON i.id_insumo = r.id_insumo
        WHERE @id_producto IS NULL OR r.id_producto = @id_producto
        ORDER BY p.nombre_producto, i.nombre_insumo
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_empleado_insertar
-- =========================================

CREATE PROCEDURE sp_empleado_insertar
    @nombre VARCHAR(50),
    @apellido VARCHAR(50),
    @correo VARCHAR(100),
    @contrasena_hash VARCHAR(255),
    @id_rol INT,
    @activo BIT = 1,
    @id_empleado INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NULLIF(LTRIM(RTRIM(@contrasena_hash)), '') IS NULL
            THROW 50100, 'La contraseña (hash) es obligatoria.', 1
        IF NULLIF(LTRIM(RTRIM(@nombre)), '') IS NULL OR NULLIF(LTRIM(RTRIM(@apellido)), '') IS NULL
            THROW 50100, 'Nombre y apellido del empleado son obligatorios.', 1
        IF NULLIF(LTRIM(RTRIM(@correo)), '') IS NULL OR @correo NOT LIKE '%_@_%._%'
            THROW 50100, 'El correo del empleado no es válido.', 1
        IF NOT EXISTS (SELECT 1 FROM dbo.ROL WHERE id_rol = @id_rol)
            THROW 50100, 'El rol indicado no existe.', 1

        BEGIN TRANSACTION;

        INSERT INTO dbo.EMPLEADO (nombre, apellido, correo, contrasena_hash, id_rol, activo)
        VALUES (LTRIM(RTRIM(@nombre)), LTRIM(RTRIM(@apellido)), LTRIM(RTRIM(@correo)), LTRIM(RTRIM(@contrasena_hash)), @id_rol, @activo)

        SET @id_empleado = SCOPE_IDENTITY()

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un empleado con ese correo.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del empleado no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_empleado_actualizar
-- =========================================

CREATE PROCEDURE sp_empleado_actualizar
    @id_empleado INT,
    @nombre VARCHAR(50),
    @apellido VARCHAR(50),
    @correo VARCHAR(100),
    @contrasena_hash VARCHAR(255) = NULL,
    @id_rol INT,
    @activo BIT = 1
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.EMPLEADO WHERE id_empleado = @id_empleado)
            THROW 50101, 'El empleado no existe.', 1
        IF NULLIF(LTRIM(RTRIM(@nombre)), '') IS NULL OR NULLIF(LTRIM(RTRIM(@apellido)), '') IS NULL
            THROW 50100, 'Nombre y apellido del empleado son obligatorios.', 1
        IF NULLIF(LTRIM(RTRIM(@correo)), '') IS NULL OR @correo NOT LIKE '%_@_%._%'
            THROW 50100, 'El correo del empleado no es válido.', 1
        IF NOT EXISTS (SELECT 1 FROM dbo.ROL WHERE id_rol = @id_rol)
            THROW 50100, 'El rol indicado no existe.', 1
        IF dbo.fn_tiene_permiso(@id_empleado, 'roles.editar') = 1
           AND (@activo = 0 OR NOT EXISTS (SELECT 1 FROM dbo.ROL_PERMISO rp INNER JOIN dbo.PERMISO p ON p.id_permiso = rp.id_permiso WHERE rp.id_rol = @id_rol AND p.clave = 'roles.editar'))
           AND NOT EXISTS (SELECT 1 FROM dbo.EMPLEADO e WHERE e.id_empleado <> @id_empleado AND dbo.fn_tiene_permiso(e.id_empleado, 'roles.editar') = 1)
            THROW 50105, 'Debe quedar al menos un empleado activo con permiso para editar roles.', 1

        BEGIN TRANSACTION;

        UPDATE dbo.EMPLEADO
        SET nombre = LTRIM(RTRIM(@nombre)),
            apellido = LTRIM(RTRIM(@apellido)),
            correo = LTRIM(RTRIM(@correo)),
            contrasena_hash = COALESCE(NULLIF(LTRIM(RTRIM(@contrasena_hash)), ''), contrasena_hash),
            id_rol = @id_rol,
            activo = @activo
        WHERE id_empleado = @id_empleado

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un empleado con ese correo.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del empleado no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_empleado_eliminar
-- =========================================

CREATE PROCEDURE sp_empleado_eliminar
    @id_empleado INT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.EMPLEADO WHERE id_empleado = @id_empleado)
            THROW 50101, 'El empleado no existe.', 1
        IF dbo.fn_tiene_permiso(@id_empleado, 'roles.editar') = 1
           AND NOT EXISTS (SELECT 1 FROM dbo.EMPLEADO e WHERE e.id_empleado <> @id_empleado AND dbo.fn_tiene_permiso(e.id_empleado, 'roles.editar') = 1)
            THROW 50105, 'Debe quedar al menos un empleado activo con permiso para editar roles.', 1

        BEGIN TRANSACTION;

        DELETE FROM dbo.EMPLEADO WHERE id_empleado = @id_empleado

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar el empleado: tiene pedidos, turnos o facturas asociados. Puede desactivarlo.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_empleado_consultar
-- =========================================

CREATE PROCEDURE sp_empleado_consultar
    @id_empleado INT = NULL
AS
BEGIN
    SET NOCOUNT ON

    BEGIN TRY
        IF @id_empleado IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.EMPLEADO WHERE id_empleado = @id_empleado)
            THROW 50101, 'El empleado no existe.', 1

        SELECT id_empleado, nombre, apellido, correo, id_rol, fecha_ingreso, activo
        FROM dbo.EMPLEADO
        WHERE @id_empleado IS NULL OR id_empleado = @id_empleado
        ORDER BY apellido, nombre
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_turno_caja_insertar
-- =========================================

CREATE PROCEDURE sp_turno_caja_insertar
    @id_empleado INT,
    @monto_inicial DECIMAL(10,2),
    @id_turno INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF @monto_inicial IS NULL OR @monto_inicial < 0
            THROW 50100, 'El monto inicial no es válido.', 1
        IF NOT EXISTS (SELECT 1 FROM dbo.CAJERO c INNER JOIN dbo.EMPLEADO e ON e.id_empleado = c.id_empleado WHERE c.id_empleado = @id_empleado AND e.activo = 1)
            THROW 50100, 'El empleado no es un cajero activo.', 1

        BEGIN TRANSACTION;

        INSERT INTO dbo.TURNO_CAJA (id_empleado, monto_inicial)
        VALUES (@id_empleado, @monto_inicial)

        SET @id_turno = SCOPE_IDENTITY()

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ese cajero ya tiene un turno abierto.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del turno no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_turno_caja_actualizar
-- =========================================

CREATE PROCEDURE sp_turno_caja_actualizar
    @id_turno INT,
    @id_empleado INT,
    @monto_inicial DECIMAL(10,2),
    @fecha_cierre DATETIME = NULL,
    @monto_final DECIMAL(10,2) = NULL
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.TURNO_CAJA WHERE id_turno = @id_turno)
            THROW 50101, 'El turno de caja no existe.', 1
        IF @fecha_cierre IS NOT NULL AND @monto_final IS NULL
            THROW 50100, 'Para cerrar el turno debe indicar el monto final.', 1
        IF @monto_inicial IS NULL OR @monto_inicial < 0
            THROW 50100, 'El monto inicial no es válido.', 1
        IF NOT EXISTS (SELECT 1 FROM dbo.CAJERO c INNER JOIN dbo.EMPLEADO e ON e.id_empleado = c.id_empleado WHERE c.id_empleado = @id_empleado AND e.activo = 1)
            THROW 50100, 'El empleado no es un cajero activo.', 1

        BEGIN TRANSACTION;

        UPDATE dbo.TURNO_CAJA
        SET id_empleado = @id_empleado,
            monto_inicial = @monto_inicial,
            fecha_cierre = @fecha_cierre,
            monto_final = @monto_final
        WHERE id_turno = @id_turno

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ese cajero ya tiene un turno abierto.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del turno no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_turno_caja_eliminar
-- =========================================

CREATE PROCEDURE sp_turno_caja_eliminar
    @id_turno INT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.TURNO_CAJA WHERE id_turno = @id_turno)
            THROW 50101, 'El turno de caja no existe.', 1

        BEGIN TRANSACTION;

        DELETE FROM dbo.TURNO_CAJA WHERE id_turno = @id_turno

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar el turno: tiene facturas asociadas.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_turno_caja_consultar
-- =========================================

CREATE PROCEDURE sp_turno_caja_consultar
    @id_turno INT = NULL
AS
BEGIN
    SET NOCOUNT ON

    BEGIN TRY
        IF @id_turno IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.TURNO_CAJA WHERE id_turno = @id_turno)
            THROW 50101, 'El turno de caja no existe.', 1

        SELECT id_turno, id_empleado, monto_inicial, fecha_cierre, monto_final
        FROM dbo.TURNO_CAJA
        WHERE @id_turno IS NULL OR id_turno = @id_turno
        ORDER BY fecha_apertura DESC
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_permiso_insertar
-- =========================================

CREATE PROCEDURE sp_permiso_insertar
    @clave VARCHAR(60),
    @modulo VARCHAR(50),
    @descripcion VARCHAR(150),
    @id_permiso INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        SET @clave = LOWER(LTRIM(RTRIM(@clave)))

        IF NULLIF(@clave, '') IS NULL OR @clave NOT LIKE '%_._%' OR @clave LIKE '% %'
            THROW 50100, 'La clave del permiso debe tener el formato modulo.accion (sin espacios).', 1
        IF NULLIF(LTRIM(RTRIM(@modulo)), '') IS NULL OR NULLIF(LTRIM(RTRIM(@descripcion)), '') IS NULL
            THROW 50100, 'El módulo y la descripción del permiso son obligatorios.', 1

        BEGIN TRANSACTION;

        INSERT INTO dbo.PERMISO (clave, modulo, descripcion)
        VALUES (@clave, LTRIM(RTRIM(@modulo)), LTRIM(RTRIM(@descripcion)))

        SET @id_permiso = SCOPE_IDENTITY()

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un permiso con esa clave.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_permiso_actualizar
-- =========================================

CREATE PROCEDURE sp_permiso_actualizar
    @id_permiso INT,
    @clave VARCHAR(60),
    @modulo VARCHAR(50),
    @descripcion VARCHAR(150)
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    DECLARE @clave_actual VARCHAR(60)

    BEGIN TRY
        SET @clave = LOWER(LTRIM(RTRIM(@clave)))

        SELECT @clave_actual = clave FROM dbo.PERMISO WHERE id_permiso = @id_permiso

        IF @clave_actual IS NULL
            THROW 50101, 'El permiso no existe.', 1
        IF NULLIF(@clave, '') IS NULL OR @clave NOT LIKE '%_._%' OR @clave LIKE '% %'
            THROW 50100, 'La clave del permiso debe tener el formato modulo.accion (sin espacios).', 1
        IF NULLIF(LTRIM(RTRIM(@modulo)), '') IS NULL OR NULLIF(LTRIM(RTRIM(@descripcion)), '') IS NULL
            THROW 50100, 'El módulo y la descripción del permiso son obligatorios.', 1
        -- La aplicación consulta los permisos por su clave: renombrar roles.editar dejaría a todos sin acceso a roles
        IF @clave_actual = 'roles.editar' AND @clave <> @clave_actual
            THROW 50105, 'La clave roles.editar no se puede cambiar.', 1

        BEGIN TRANSACTION;

        UPDATE dbo.PERMISO
        SET clave = @clave,
            modulo = LTRIM(RTRIM(@modulo)),
            descripcion = LTRIM(RTRIM(@descripcion))
        WHERE id_permiso = @id_permiso

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un permiso con esa clave.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_permiso_eliminar
-- =========================================

CREATE PROCEDURE sp_permiso_eliminar
    @id_permiso INT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    DECLARE @clave VARCHAR(60)

    BEGIN TRY
        SELECT @clave = clave FROM dbo.PERMISO WHERE id_permiso = @id_permiso

        IF @clave IS NULL
            THROW 50101, 'El permiso no existe.', 1
        IF @clave = 'roles.editar'
            THROW 50105, 'El permiso roles.editar no se puede eliminar: sin él nadie podría administrar los roles.', 1

        BEGIN TRANSACTION;

        -- ROL_PERMISO se borra en cascada
        DELETE FROM dbo.PERMISO WHERE id_permiso = @id_permiso

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_permiso_consultar
-- =========================================

CREATE PROCEDURE sp_permiso_consultar
    @id_permiso INT = NULL
AS
BEGIN
    SET NOCOUNT ON

    BEGIN TRY
        IF @id_permiso IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.PERMISO WHERE id_permiso = @id_permiso)
            THROW 50101, 'El permiso no existe.', 1

        SELECT p.id_permiso, p.clave, p.modulo, p.descripcion,
               (SELECT COUNT(*) FROM dbo.ROL_PERMISO rp WHERE rp.id_permiso = p.id_permiso) AS roles_que_lo_tienen
        FROM dbo.PERMISO p
        WHERE @id_permiso IS NULL OR p.id_permiso = @id_permiso
        ORDER BY p.modulo, p.clave
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_mesero_insertar
-- =========================================

CREATE PROCEDURE sp_mesero_insertar
    @id_empleado INT,
    @zona_asignada VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.EMPLEADO WHERE id_empleado = @id_empleado)
            THROW 50101, 'El empleado no existe.', 1
        IF NULLIF(LTRIM(RTRIM(@zona_asignada)), '') IS NULL
            THROW 50100, 'La zona asignada del mesero es obligatoria.', 1
        -- Especialización disjunta: un empleado pertenece a lo sumo a una subclase
        IF EXISTS (SELECT 1 FROM dbo.COCINERO WHERE id_empleado = @id_empleado)
           OR EXISTS (SELECT 1 FROM dbo.CAJERO WHERE id_empleado = @id_empleado)
            THROW 50106, 'El empleado ya está registrado como cocinero o cajero.', 1

        BEGIN TRANSACTION;

        INSERT INTO dbo.MESERO (id_empleado, zona_asignada)
        VALUES (@id_empleado, LTRIM(RTRIM(@zona_asignada)))

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'El empleado ya está registrado como mesero.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_mesero_actualizar
-- =========================================

CREATE PROCEDURE sp_mesero_actualizar
    @id_empleado INT,
    @zona_asignada VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.MESERO WHERE id_empleado = @id_empleado)
            THROW 50101, 'El mesero no existe.', 1
        IF NULLIF(LTRIM(RTRIM(@zona_asignada)), '') IS NULL
            THROW 50100, 'La zona asignada del mesero es obligatoria.', 1

        BEGIN TRANSACTION;

        UPDATE dbo.MESERO
        SET zona_asignada = LTRIM(RTRIM(@zona_asignada))
        WHERE id_empleado = @id_empleado

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_mesero_eliminar
-- =========================================

CREATE PROCEDURE sp_mesero_eliminar
    @id_empleado INT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.MESERO WHERE id_empleado = @id_empleado)
            THROW 50101, 'El mesero no existe.', 1

        BEGIN TRANSACTION;

        DELETE FROM dbo.MESERO WHERE id_empleado = @id_empleado

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_mesero_consultar
-- =========================================

CREATE PROCEDURE sp_mesero_consultar
    @id_empleado INT = NULL
AS
BEGIN
    SET NOCOUNT ON

    BEGIN TRY
        IF @id_empleado IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.MESERO WHERE id_empleado = @id_empleado)
            THROW 50101, 'El mesero no existe.', 1

        SELECT m.id_empleado, e.nombre, e.apellido, e.activo, m.zona_asignada
        FROM dbo.MESERO m
        INNER JOIN dbo.EMPLEADO e ON e.id_empleado = m.id_empleado
        WHERE @id_empleado IS NULL OR m.id_empleado = @id_empleado
        ORDER BY e.apellido, e.nombre
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_cocinero_insertar
-- =========================================

CREATE PROCEDURE sp_cocinero_insertar
    @id_empleado INT,
    @id_estacion INT,
    @especialidad VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.EMPLEADO WHERE id_empleado = @id_empleado)
            THROW 50101, 'El empleado no existe.', 1
        IF NOT EXISTS (SELECT 1 FROM dbo.ESTACION WHERE id_estacion = @id_estacion)
            THROW 50100, 'La estación indicada no existe.', 1
        IF NULLIF(LTRIM(RTRIM(@especialidad)), '') IS NULL
            THROW 50100, 'La especialidad del cocinero es obligatoria.', 1
        IF EXISTS (SELECT 1 FROM dbo.MESERO WHERE id_empleado = @id_empleado)
           OR EXISTS (SELECT 1 FROM dbo.CAJERO WHERE id_empleado = @id_empleado)
            THROW 50106, 'El empleado ya está registrado como mesero o cajero.', 1

        BEGIN TRANSACTION;

        INSERT INTO dbo.COCINERO (id_empleado, id_estacion, especialidad)
        VALUES (@id_empleado, @id_estacion, LTRIM(RTRIM(@especialidad)))

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'El empleado ya está registrado como cocinero.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_cocinero_actualizar
-- =========================================

CREATE PROCEDURE sp_cocinero_actualizar
    @id_empleado INT,
    @id_estacion INT,
    @especialidad VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.COCINERO WHERE id_empleado = @id_empleado)
            THROW 50101, 'El cocinero no existe.', 1
        IF NOT EXISTS (SELECT 1 FROM dbo.ESTACION WHERE id_estacion = @id_estacion)
            THROW 50100, 'La estación indicada no existe.', 1
        IF NULLIF(LTRIM(RTRIM(@especialidad)), '') IS NULL
            THROW 50100, 'La especialidad del cocinero es obligatoria.', 1

        BEGIN TRANSACTION;

        UPDATE dbo.COCINERO
        SET id_estacion = @id_estacion,
            especialidad = LTRIM(RTRIM(@especialidad))
        WHERE id_empleado = @id_empleado

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_cocinero_eliminar
-- =========================================

CREATE PROCEDURE sp_cocinero_eliminar
    @id_empleado INT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.COCINERO WHERE id_empleado = @id_empleado)
            THROW 50101, 'El cocinero no existe.', 1

        BEGIN TRANSACTION;

        DELETE FROM dbo.COCINERO WHERE id_empleado = @id_empleado

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_cocinero_consultar
-- =========================================

CREATE PROCEDURE sp_cocinero_consultar
    @id_empleado INT = NULL
AS
BEGIN
    SET NOCOUNT ON

    BEGIN TRY
        IF @id_empleado IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.COCINERO WHERE id_empleado = @id_empleado)
            THROW 50101, 'El cocinero no existe.', 1

        SELECT c.id_empleado, e.nombre, e.apellido, e.activo, c.id_estacion, es.nombre_estacion, c.especialidad
        FROM dbo.COCINERO c
        INNER JOIN dbo.EMPLEADO e ON e.id_empleado = c.id_empleado
        INNER JOIN dbo.ESTACION es ON es.id_estacion = c.id_estacion
        WHERE @id_empleado IS NULL OR c.id_empleado = @id_empleado
        ORDER BY es.nombre_estacion, e.apellido, e.nombre
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_cajero_insertar
-- =========================================

CREATE PROCEDURE sp_cajero_insertar
    @id_empleado INT,
    @codigo_caja VARCHAR(20)
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.EMPLEADO WHERE id_empleado = @id_empleado)
            THROW 50101, 'El empleado no existe.', 1
        IF NULLIF(LTRIM(RTRIM(@codigo_caja)), '') IS NULL
            THROW 50100, 'El código de caja es obligatorio.', 1
        IF EXISTS (SELECT 1 FROM dbo.MESERO WHERE id_empleado = @id_empleado)
           OR EXISTS (SELECT 1 FROM dbo.COCINERO WHERE id_empleado = @id_empleado)
            THROW 50106, 'El empleado ya está registrado como mesero o cocinero.', 1

        BEGIN TRANSACTION;

        INSERT INTO dbo.CAJERO (id_empleado, codigo_caja)
        VALUES (@id_empleado, LTRIM(RTRIM(@codigo_caja)))

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'El empleado ya es cajero o ese código de caja ya está asignado.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_cajero_actualizar
-- =========================================

CREATE PROCEDURE sp_cajero_actualizar
    @id_empleado INT,
    @codigo_caja VARCHAR(20)
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.CAJERO WHERE id_empleado = @id_empleado)
            THROW 50101, 'El cajero no existe.', 1
        IF NULLIF(LTRIM(RTRIM(@codigo_caja)), '') IS NULL
            THROW 50100, 'El código de caja es obligatorio.', 1

        BEGIN TRANSACTION;

        UPDATE dbo.CAJERO
        SET codigo_caja = LTRIM(RTRIM(@codigo_caja))
        WHERE id_empleado = @id_empleado

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ese código de caja ya está asignado a otro cajero.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_cajero_eliminar
-- =========================================

CREATE PROCEDURE sp_cajero_eliminar
    @id_empleado INT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.CAJERO WHERE id_empleado = @id_empleado)
            THROW 50101, 'El cajero no existe.', 1

        BEGIN TRANSACTION;

        -- Con un turno abierto, la caja quedaría sin responsable
        IF EXISTS (SELECT 1 FROM dbo.TURNO_CAJA WITH (UPDLOCK, HOLDLOCK) WHERE id_empleado = @id_empleado AND fecha_cierre IS NULL)
            THROW 50107, 'El cajero tiene un turno de caja abierto. Ciérrelo antes de quitarle el puesto.', 1

        DELETE FROM dbo.CAJERO WHERE id_empleado = @id_empleado

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_cajero_consultar
-- =========================================

CREATE PROCEDURE sp_cajero_consultar
    @id_empleado INT = NULL
AS
BEGIN
    SET NOCOUNT ON

    BEGIN TRY
        IF @id_empleado IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.CAJERO WHERE id_empleado = @id_empleado)
            THROW 50101, 'El cajero no existe.', 1

        SELECT c.id_empleado, e.nombre, e.apellido, e.activo, c.codigo_caja,
               CASE WHEN EXISTS (SELECT 1 FROM dbo.TURNO_CAJA t WHERE t.id_empleado = c.id_empleado AND t.fecha_cierre IS NULL)
                    THEN 1 ELSE 0 END AS turno_abierto
        FROM dbo.CAJERO c
        INNER JOIN dbo.EMPLEADO e ON e.id_empleado = c.id_empleado
        WHERE @id_empleado IS NULL OR c.id_empleado = @id_empleado
        ORDER BY c.codigo_caja
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_registrar_pedido
-- =========================================

CREATE PROCEDURE sp_registrar_pedido
    @token_idempotencia VARCHAR(100),
    @id_mesa INT,
    @id_empleado INT,
    @observaciones VARCHAR(255),
    @json_lineas NVARCHAR(MAX),
    @nuevo_id_pedido INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    DECLARE @lineas TABLE (num_linea INT NULL, id_producto INT NULL, cantidad INT NULL, notas VARCHAR(200) NULL, precio DECIMAL(10,2) NULL)
    DECLARE @requerido TABLE (id_insumo INT PRIMARY KEY, cantidad DECIMAL(14,2) NOT NULL)
    DECLARE @descontados TABLE (id_insumo INT PRIMARY KEY)
    DECLARE @estado_mesa VARCHAR(20), @faltante VARCHAR(100), @mensaje NVARCHAR(300)

    BEGIN TRY
        SELECT @nuevo_id_pedido = id_pedido
        FROM dbo.PEDIDO
        WHERE token_idempotencia = @token_idempotencia

        IF @nuevo_id_pedido IS NOT NULL
            RETURN 1;

        IF @token_idempotencia IS NULL OR LEN(@token_idempotencia) = 0
            THROW 50040, 'Falta el identificador de la solicitud (token).', 1

        IF ISJSON(ISNULL(@json_lineas, N'')) = 0
            THROW 50041, 'Las líneas del pedido no tienen un formato válido.', 1

        INSERT INTO @lineas (num_linea, id_producto, cantidad, notas)
        SELECT
            TRY_CAST(JSON_VALUE(value, '$.num_linea') AS INT),
            TRY_CAST(JSON_VALUE(value, '$.id_producto') AS INT),
            TRY_CAST(JSON_VALUE(value, '$.cantidad') AS INT),
            NULLIF(LTRIM(RTRIM(LEFT(JSON_VALUE(value, '$.notas'), 200))), '')
        FROM OPENJSON(@json_lineas)

        IF NOT EXISTS (SELECT 1 FROM @lineas)
            THROW 50042, 'El pedido debe tener al menos un producto.', 1

        IF EXISTS (SELECT 1 FROM @lineas WHERE num_linea IS NULL OR num_linea < 1 OR cantidad IS NULL OR cantidad < 1)
            THROW 50043, 'Cada línea debe tener un número de línea y una cantidad mayor que cero.', 1

        IF EXISTS (SELECT num_linea FROM @lineas GROUP BY num_linea HAVING COUNT(*) > 1)
            THROW 50044, 'Hay números de línea repetidos en el pedido.', 1

        UPDATE l
        SET l.precio = p.precio
        FROM @lineas l
        INNER JOIN dbo.PRODUCTO p ON p.id_producto = l.id_producto AND p.activo = 1

        IF EXISTS (SELECT 1 FROM @lineas WHERE precio IS NULL)
            THROW 50045, 'Uno de los productos no existe o ya no está disponible.', 1

        IF dbo.fn_tiene_permiso(@id_empleado, 'mesero.usar') = 0
            THROW 50046, 'El empleado que registra el pedido no existe, está inactivo o no tiene permiso para tomar pedidos.', 1

        BEGIN TRANSACTION;

        SELECT @estado_mesa = estado
        FROM dbo.MESA WITH (UPDLOCK, HOLDLOCK)
        WHERE id_mesa = @id_mesa

        IF @estado_mesa IS NULL
            THROW 50001, 'La mesa especificada no existe.', 1

        IF @estado_mesa = 'MANTENIMIENTO'
            THROW 50047, 'La mesa está en mantenimiento.', 1

        INSERT INTO @requerido (id_insumo, cantidad)
        SELECT r.id_insumo, SUM(l.cantidad * r.cantidad)
        FROM @lineas l
        INNER JOIN dbo.RECETA r ON r.id_producto = l.id_producto
        GROUP BY r.id_insumo

        UPDATE i
        SET i.stock_actual = i.stock_actual - q.cantidad
        OUTPUT inserted.id_insumo INTO @descontados (id_insumo)
        FROM dbo.INSUMO i
        INNER JOIN @requerido q ON q.id_insumo = i.id_insumo
        WHERE i.stock_actual >= q.cantidad

        SELECT TOP (1) @faltante = i.nombre_insumo
        FROM @requerido q
        INNER JOIN dbo.INSUMO i ON i.id_insumo = q.id_insumo
        WHERE NOT EXISTS (SELECT 1 FROM @descontados d WHERE d.id_insumo = q.id_insumo)
        ORDER BY i.nombre_insumo

        IF @faltante IS NOT NULL
        BEGIN
            SET @mensaje = CONCAT(N'Stock insuficiente de ', @faltante, N' para completar el pedido.');
            THROW 50002, @mensaje, 1
        END

        INSERT INTO dbo.PEDIDO (token_idempotencia, id_mesa, id_empleado, observaciones, subtotal)
        SELECT @token_idempotencia, @id_mesa, @id_empleado, @observaciones, SUM(cantidad * precio)
        FROM @lineas

        SET @nuevo_id_pedido = SCOPE_IDENTITY()

        INSERT INTO dbo.DETALLE_PEDIDO (id_pedido, num_linea, id_producto, cantidad, precio_unitario, notas)
        SELECT @nuevo_id_pedido, num_linea, id_producto, cantidad, precio, notas
        FROM @lineas

        UPDATE dbo.MESA SET estado = 'OCUPADA' WHERE id_mesa = @id_mesa

        COMMIT TRANSACTION;
        RETURN 0;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;

        IF ERROR_NUMBER() IN (2601, 2627)
        BEGIN
            SELECT @nuevo_id_pedido = id_pedido
            FROM dbo.PEDIDO
            WHERE token_idempotencia = @token_idempotencia

            IF @nuevo_id_pedido IS NOT NULL
                RETURN 1;

            THROW 50007, 'Esa mesa ya tiene un pedido abierto. Agregue los productos al pedido existente.', 1
        END;

        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_agregar_items_pedido
-- =========================================

CREATE PROCEDURE sp_agregar_items_pedido
    @id_pedido INT,
    @json_lineas NVARCHAR(MAX)
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    DECLARE @lineas TABLE (orden INT NOT NULL, id_producto INT NULL, cantidad INT NULL, notas VARCHAR(200) NULL, precio DECIMAL(10,2) NULL)
    DECLARE @requerido TABLE (id_insumo INT PRIMARY KEY, cantidad DECIMAL(14,2) NOT NULL)
    DECLARE @descontados TABLE (id_insumo INT PRIMARY KEY)
    DECLARE @estado VARCHAR(20), @ultima_linea INT, @faltante VARCHAR(100), @mensaje NVARCHAR(300)

    BEGIN TRY
        IF ISJSON(ISNULL(@json_lineas, N'')) = 0
            THROW 50041, 'Las líneas del pedido no tienen un formato válido.', 1

        INSERT INTO @lineas (orden, id_producto, cantidad, notas)
        SELECT
            CAST([key] AS INT),
            TRY_CAST(JSON_VALUE(value, '$.id_producto') AS INT),
            TRY_CAST(JSON_VALUE(value, '$.cantidad') AS INT),
            NULLIF(LTRIM(RTRIM(LEFT(JSON_VALUE(value, '$.notas'), 200))), '')
        FROM OPENJSON(@json_lineas)

        IF NOT EXISTS (SELECT 1 FROM @lineas)
            THROW 50042, 'El pedido debe tener al menos un producto.', 1

        IF EXISTS (SELECT 1 FROM @lineas WHERE cantidad IS NULL OR cantidad < 1)
            THROW 50043, 'Cada línea debe tener una cantidad mayor que cero.', 1

        UPDATE l
        SET l.precio = p.precio
        FROM @lineas l
        INNER JOIN dbo.PRODUCTO p ON p.id_producto = l.id_producto AND p.activo = 1

        IF EXISTS (SELECT 1 FROM @lineas WHERE precio IS NULL)
            THROW 50045, 'Uno de los productos no existe o ya no está disponible.', 1

        BEGIN TRANSACTION;

        SELECT @estado = estado
        FROM dbo.PEDIDO WITH (UPDLOCK, HOLDLOCK)
        WHERE id_pedido = @id_pedido

        IF @estado IS NULL OR @estado NOT IN ('ABIERTO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO')
            THROW 50048, 'El pedido no existe o ya fue facturado o anulado.', 1

        SELECT @ultima_linea = ISNULL(MAX(num_linea), 0)
        FROM dbo.DETALLE_PEDIDO
        WHERE id_pedido = @id_pedido

        INSERT INTO @requerido (id_insumo, cantidad)
        SELECT r.id_insumo, SUM(l.cantidad * r.cantidad)
        FROM @lineas l
        INNER JOIN dbo.RECETA r ON r.id_producto = l.id_producto
        GROUP BY r.id_insumo

        UPDATE i
        SET i.stock_actual = i.stock_actual - q.cantidad
        OUTPUT inserted.id_insumo INTO @descontados (id_insumo)
        FROM dbo.INSUMO i
        INNER JOIN @requerido q ON q.id_insumo = i.id_insumo
        WHERE i.stock_actual >= q.cantidad

        SELECT TOP (1) @faltante = i.nombre_insumo
        FROM @requerido q
        INNER JOIN dbo.INSUMO i ON i.id_insumo = q.id_insumo
        WHERE NOT EXISTS (SELECT 1 FROM @descontados d WHERE d.id_insumo = q.id_insumo)
        ORDER BY i.nombre_insumo

        IF @faltante IS NOT NULL
        BEGIN
            SET @mensaje = CONCAT(N'Stock insuficiente de ', @faltante, N' para agregar los productos.');
            THROW 50002, @mensaje, 1
        END

        INSERT INTO dbo.DETALLE_PEDIDO (id_pedido, num_linea, id_producto, cantidad, precio_unitario, notas)
        SELECT @id_pedido, @ultima_linea + ROW_NUMBER() OVER (ORDER BY orden), id_producto, cantidad, precio, notas
        FROM @lineas

        UPDATE dbo.PEDIDO
        SET subtotal = (SELECT SUM(cantidad * precio_unitario) FROM dbo.DETALLE_PEDIDO WHERE id_pedido = @id_pedido),
            estado = 'ABIERTO'
        WHERE id_pedido = @id_pedido

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_cobrar_cuenta
-- =========================================

CREATE PROCEDURE sp_cobrar_cuenta
    @id_pedido INT,
    @id_cliente INT = NULL,
    @id_cajero INT,
    @descuento DECIMAL(10,2) = 0,
    @json_pagos NVARCHAR(MAX),
    @numero_factura_out VARCHAR(50) OUTPUT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    DECLARE @pagos TABLE (id_metodo_pago INT NULL, monto DECIMAL(10,2) NULL, referencia VARCHAR(100) NULL)
    DECLARE @subtotal DECIMAL(10,2), @id_mesa INT, @id_turno INT
    DECLARE @monto_descuento DECIMAL(10,2), @base_imponible DECIMAL(10,2)
    DECLARE @iva DECIMAL(10,2), @servicio DECIMAL(10,2), @total DECIMAL(10,2)
    DECLARE @total_pagado DECIMAL(12,2), @id_factura INT

    BEGIN TRY
        SET @descuento = ISNULL(@descuento, 0)

        IF @descuento < 0 OR @descuento > 100
            THROW 50049, 'El descuento debe estar entre 0 y 100 %.', 1

        IF ISJSON(ISNULL(@json_pagos, N'')) = 0
            THROW 50050, 'Los pagos no tienen un formato válido.', 1

        INSERT INTO @pagos (id_metodo_pago, monto, referencia)
        SELECT
            TRY_CAST(JSON_VALUE(value, '$.id_metodo_pago') AS INT),
            TRY_CAST(JSON_VALUE(value, '$.monto') AS DECIMAL(10,2)),
            NULLIF(LTRIM(RTRIM(LEFT(JSON_VALUE(value, '$.referencia'), 100))), '')
        FROM OPENJSON(@json_pagos)

        IF NOT EXISTS (SELECT 1 FROM @pagos)
            THROW 50051, 'Debe registrar al menos un pago.', 1

        IF EXISTS (SELECT 1 FROM @pagos WHERE monto IS NULL OR monto <= 0)
            THROW 50052, 'Cada pago debe tener un monto mayor que cero.', 1

        IF EXISTS (SELECT 1 FROM @pagos pg WHERE NOT EXISTS (SELECT 1 FROM dbo.METODO_PAGO mp WHERE mp.id_metodo_pago = pg.id_metodo_pago))
            THROW 50053, 'Uno de los métodos de pago no existe.', 1

        IF @id_cliente IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.CLIENTE WHERE id_cliente = @id_cliente)
            THROW 50054, 'El cliente indicado no existe.', 1

        IF dbo.fn_tiene_permiso(@id_cajero, 'caja.usar') = 0
            THROW 50059, 'El empleado no está activo o no tiene permiso para cobrar.', 1

        SELECT @id_mesa = id_mesa FROM dbo.PEDIDO WHERE id_pedido = @id_pedido

        BEGIN TRANSACTION;

        SELECT @id_mesa = id_mesa
        FROM dbo.MESA WITH (UPDLOCK, HOLDLOCK)
        WHERE id_mesa = @id_mesa

        SELECT @subtotal = subtotal
        FROM dbo.PEDIDO WITH (UPDLOCK, HOLDLOCK)
        WHERE id_pedido = @id_pedido AND estado IN ('ABIERTO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO')

        IF @subtotal IS NULL
            THROW 50003, 'El pedido no existe o ya fue facturado/anulado.', 1

        SELECT @id_turno = id_turno
        FROM dbo.TURNO_CAJA WITH (UPDLOCK, HOLDLOCK)
        WHERE id_empleado = @id_cajero AND fecha_cierre IS NULL

        IF @id_turno IS NULL
            THROW 50004, 'El cajero no tiene un turno de caja abierto.', 1

        SET @monto_descuento = @subtotal * (@descuento / 100.00)
        SET @base_imponible = @subtotal - @monto_descuento
        SET @iva = @base_imponible * 0.13
        SET @servicio = @base_imponible * 0.10
        SET @total = @base_imponible + @iva + @servicio

        SELECT @total_pagado = SUM(monto) FROM @pagos

        IF @total_pagado < @total
            THROW 50005, 'El monto acumulado de pagos es inferior al total de la factura.', 1

        SET @numero_factura_out = 'FAC-' + RIGHT('00000000' + CAST(@id_pedido AS VARCHAR(8)), 8)

        INSERT INTO dbo.FACTURA (numero_factura, id_pedido, id_cliente, id_empleado, id_turno, subtotal, descuento, impuesto_ventas, servicio, total, vuelto)
        VALUES (@numero_factura_out, @id_pedido, @id_cliente, @id_cajero, @id_turno, @subtotal, @monto_descuento, @iva, @servicio, @total, @total_pagado - @total)

        SET @id_factura = SCOPE_IDENTITY()

        INSERT INTO dbo.PAGO (id_factura, id_metodo_pago, monto, referencia)
        SELECT @id_factura, id_metodo_pago, monto, referencia
        FROM @pagos

        UPDATE dbo.PEDIDO SET estado = 'FACTURADO' WHERE id_pedido = @id_pedido
        UPDATE dbo.MESA SET estado = 'DISPONIBLE' WHERE id_mesa = @id_mesa

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_anular_pedido
-- =========================================

CREATE PROCEDURE sp_anular_pedido
    @id_pedido INT,
    @id_empleado INT,
    @motivo VARCHAR(255)
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    DECLARE @id_mesa INT, @estado_pedido VARCHAR(20)

    BEGIN TRY
        IF @motivo IS NULL OR LEN(LTRIM(RTRIM(@motivo))) = 0
            THROW 50055, 'Debe indicar el motivo de la anulación.', 1

        IF dbo.fn_tiene_permiso(@id_empleado, 'pedidos.anular') = 0
            THROW 50046, 'El empleado que anula no existe, está inactivo o no tiene permiso para anular pedidos.', 1

        SELECT @id_mesa = id_mesa FROM dbo.PEDIDO WHERE id_pedido = @id_pedido

        BEGIN TRANSACTION;

        SELECT @id_mesa = id_mesa
        FROM dbo.MESA WITH (UPDLOCK, HOLDLOCK)
        WHERE id_mesa = @id_mesa

        SELECT @estado_pedido = estado
        FROM dbo.PEDIDO WITH (UPDLOCK, HOLDLOCK)
        WHERE id_pedido = @id_pedido

        IF @estado_pedido IS NULL OR @estado_pedido IN ('FACTURADO', 'ANULADO')
            THROW 50006, 'No se puede anular un pedido inexistente, facturado o ya anulado.', 1

        UPDATE i
        SET i.stock_actual = i.stock_actual + q.cantidad
        FROM dbo.INSUMO i
        INNER JOIN (
            SELECT r.id_insumo, SUM(dp.cantidad * r.cantidad) AS cantidad
            FROM dbo.DETALLE_PEDIDO dp
            INNER JOIN dbo.RECETA r ON r.id_producto = dp.id_producto
            WHERE dp.id_pedido = @id_pedido AND dp.estado_cocina = 'PENDIENTE'
            GROUP BY r.id_insumo
        ) q ON q.id_insumo = i.id_insumo

        UPDATE dbo.PEDIDO
        SET estado = 'ANULADO',
            anulado_por = @id_empleado,
            motivo_anulacion = LTRIM(RTRIM(@motivo))
        WHERE id_pedido = @id_pedido

        UPDATE dbo.DETALLE_PEDIDO
        SET estado_cocina = 'CANCELADO',
            fecha_actualizacion = GETDATE()
        WHERE id_pedido = @id_pedido AND estado_cocina = 'PENDIENTE'

        UPDATE dbo.MESA SET estado = 'DISPONIBLE' WHERE id_mesa = @id_mesa

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_anular_factura
-- Anula una factura cobrada (nunca se borra): registra quién, por qué y cuándo.
-- Bloquea la factura (UPDLOCK, HOLDLOCK) para que dos personas no la anulen a la vez.
-- Los pagos no se tocan: trg_pago_inmutable los protege y quedan como historial.
-- =========================================

CREATE PROCEDURE sp_anular_factura
    @id_factura INT,
    @id_empleado INT,
    @motivo VARCHAR(255),
    @numero_factura VARCHAR(50) OUTPUT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    DECLARE @estado VARCHAR(20)

    BEGIN TRY
        SET @motivo = NULLIF(LTRIM(RTRIM(@motivo)), '')

        IF @motivo IS NULL
            THROW 50060, 'Debe indicar el motivo de la anulación.', 1

        IF dbo.fn_tiene_permiso(@id_empleado, 'facturas.anular') = 0
            THROW 50061, 'El empleado que anula no existe, está inactivo o no tiene permiso para anular facturas.', 1

        BEGIN TRANSACTION;

        SELECT @estado = estado, @numero_factura = numero_factura
        FROM dbo.FACTURA WITH (UPDLOCK, HOLDLOCK)
        WHERE id_factura = @id_factura

        IF @estado IS NULL
            THROW 50062, 'La factura no existe.', 1

        IF @estado = 'ANULADA'
            THROW 50063, 'Esta factura ya está anulada.', 1

        UPDATE dbo.FACTURA
        SET estado = 'ANULADA',
            anulada_por = @id_empleado,
            motivo_anulacion = @motivo,
            fecha_anulacion = GETDATE()
        WHERE id_factura = @id_factura

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_reporte_ventas
-- =========================================

CREATE PROCEDURE sp_reporte_ventas
    @fecha_inicio DATETIME,
    @fecha_fin DATETIME,
    @agrupar_por VARCHAR(20)
AS
BEGIN
    SET NOCOUNT ON

    BEGIN TRY
        IF @fecha_inicio IS NULL OR @fecha_fin IS NULL OR @fecha_fin < @fecha_inicio
            THROW 50056, 'El rango de fechas del reporte no es válido.', 1

        IF @agrupar_por NOT IN ('FACTURA', 'DIA', 'PRODUCTO', 'MESERO', 'PAGO')
            THROW 50057, 'Agrupación no válida. Use FACTURA, DIA, PRODUCTO, MESERO o PAGO.', 1

        SET TRANSACTION ISOLATION LEVEL SNAPSHOT

        IF @agrupar_por = 'FACTURA'
        BEGIN
            SELECT
                f.numero_factura AS Factura,
                f.fecha_hora AS Fecha_Hora,
                m.numero_mesa AS Mesa,
                cj.nombre + ' ' + cj.apellido AS Cajero,
                f.subtotal AS Subtotal,
                f.descuento AS Descuento,
                f.impuesto_ventas AS IVA,
                f.servicio AS Servicio,
                f.total AS Total
            FROM dbo.FACTURA f
            INNER JOIN dbo.PEDIDO p ON f.id_pedido = p.id_pedido
            INNER JOIN dbo.MESA m ON p.id_mesa = m.id_mesa
            INNER JOIN dbo.EMPLEADO cj ON f.id_empleado = cj.id_empleado
            WHERE f.fecha_hora BETWEEN @fecha_inicio AND @fecha_fin AND f.estado = 'COBRADA'
            ORDER BY f.fecha_hora ASC
        END
        ELSE IF @agrupar_por = 'DIA'
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
            ORDER BY Fecha ASC
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
            GROUP BY p.nombre_producto
            ORDER BY Ranking ASC, p.nombre_producto ASC
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
            ORDER BY Total_Vendido DESC
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
            GROUP BY mp.nombre_metodo
        END
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_respaldar_bd
-- =========================================

CREATE PROCEDURE sp_respaldar_bd
    @ruta NVARCHAR(260),
    @id_empleado INT = NULL,
    @id_respaldo INT OUTPUT,
    @archivo NVARCHAR(460) OUTPUT
AS
BEGIN
    SET NOCOUNT ON

    DECLARE @bd SYSNAME = DB_NAME()
    DECLARE @nombre NVARCHAR(200)
    DECLARE @mensaje VARCHAR(500)
    DECLARE @codigo_carpeta INT

    BEGIN TRY
        IF @ruta IS NULL OR LEN(LTRIM(RTRIM(@ruta))) = 0
            THROW 50030, 'Debe indicar la carpeta de respaldos.', 1

        SET @ruta = LTRIM(RTRIM(@ruta))

        IF RIGHT(@ruta, 1) NOT IN ('\', '/')
            SET @ruta = @ruta + N'\'

        BEGIN TRY
            EXEC @codigo_carpeta = master.dbo.xp_create_subdir @ruta
        END TRY
        BEGIN CATCH
            SET @codigo_carpeta = 1
        END CATCH

        IF @codigo_carpeta <> 0
            THROW 50032, 'SQL Server no pudo crear ni abrir la carpeta de respaldos. Créela a mano y dé permiso de escritura a la cuenta del servicio de SQL Server.', 1

        SET @nombre = @bd + N'_' + FORMAT(SYSDATETIME(), 'yyyyMMdd_HHmmss_fff') + N'.bak'
        SET @archivo = @ruta + @nombre

        BACKUP DATABASE @bd
        TO DISK = @archivo
        WITH NOINIT, CHECKSUM, STOP_ON_ERROR, NAME = @nombre, DESCRIPTION = N'La Cuchara de Luison - respaldo completo de RestauranteDB'

        RESTORE VERIFYONLY FROM DISK = @archivo WITH CHECKSUM

        INSERT INTO dbo.RESPALDO_BD (nombre_archivo, ruta_completa, id_empleado, verificado, resultado)
        VALUES (@nombre, @archivo, @id_empleado, 1, 'Respaldo generado y verificado correctamente.')

        SET @id_respaldo = SCOPE_IDENTITY()
    END TRY
    BEGIN CATCH
        SET @mensaje = LEFT(ERROR_MESSAGE(), 500)

        IF @nombre IS NOT NULL
            INSERT INTO dbo.RESPALDO_BD (nombre_archivo, ruta_completa, id_empleado, verificado, resultado)
            VALUES (@nombre, @archivo, @id_empleado, 0, @mensaje);

        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_verificar_respaldo
-- =========================================

CREATE PROCEDURE sp_verificar_respaldo
    @id_respaldo INT
AS
BEGIN
    SET NOCOUNT ON

    DECLARE @archivo NVARCHAR(460)
    DECLARE @mensaje VARCHAR(500)

    BEGIN TRY
        SELECT @archivo = ruta_completa
        FROM dbo.RESPALDO_BD
        WHERE id_respaldo = @id_respaldo

        IF @archivo IS NULL
            THROW 50031, 'El respaldo indicado no existe en el historial.', 1

        RESTORE VERIFYONLY FROM DISK = @archivo WITH CHECKSUM

        UPDATE dbo.RESPALDO_BD
        SET verificado = 1,
            resultado = 'Verificación de integridad correcta.'
        WHERE id_respaldo = @id_respaldo
    END TRY
    BEGIN CATCH
        SET @mensaje = LEFT(ERROR_MESSAGE(), 500)

        IF @archivo IS NOT NULL
            UPDATE dbo.RESPALDO_BD
            SET verificado = 0,
                resultado = @mensaje
            WHERE id_respaldo = @id_respaldo;

        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_sesion_abrir
-- =========================================

CREATE PROCEDURE sp_sesion_abrir
    @id_empleado INT,
    @token CHAR(64),
    @direccion_ip VARCHAR(45) = NULL,
    @navegador VARCHAR(255) = NULL,
    @minutos_inactividad INT = 5
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF @id_empleado IS NULL OR NULLIF(LTRIM(RTRIM(@token)), '') IS NULL
            THROW 50201, 'Faltan los datos de la sesión.', 1

        BEGIN TRANSACTION;

        DELETE FROM dbo.SESION_ACTIVA WITH (HOLDLOCK)
        WHERE id_empleado = @id_empleado
          AND ultima_actividad < DATEADD(MINUTE, -@minutos_inactividad, GETDATE())

        IF EXISTS (SELECT 1 FROM dbo.SESION_ACTIVA WITH (UPDLOCK, HOLDLOCK) WHERE id_empleado = @id_empleado)
            THROW 50200, 'Esta cuenta ya tiene una sesión abierta en otro navegador o equipo.', 1

        INSERT INTO dbo.SESION_ACTIVA (id_empleado, token, direccion_ip, navegador)
        VALUES (@id_empleado, @token, @direccion_ip, LEFT(@navegador, 255))

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50200, 'Esta cuenta ya tiene una sesión abierta en otro navegador o equipo.', 1;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_sesion_validar
-- =========================================

CREATE PROCEDURE sp_sesion_validar
    @id_empleado INT,
    @token CHAR(64),
    @vigente BIT OUTPUT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        BEGIN TRANSACTION;

        SET @vigente = CASE WHEN EXISTS (
            SELECT 1 FROM dbo.SESION_ACTIVA WHERE id_empleado = @id_empleado AND token = @token
        ) THEN 1 ELSE 0 END

        IF @vigente = 1
            UPDATE dbo.SESION_ACTIVA
            SET ultima_actividad = GETDATE()
            WHERE id_empleado = @id_empleado
              AND token = @token
              AND ultima_actividad < DATEADD(SECOND, -30, GETDATE())

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- PROCEDIMIENTO sp_sesion_cerrar
-- =========================================

CREATE PROCEDURE sp_sesion_cerrar
    @id_empleado INT,
    @token CHAR(64) = NULL
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        BEGIN TRANSACTION;

        DELETE FROM dbo.SESION_ACTIVA
        WHERE id_empleado = @id_empleado
          AND (@token IS NULL OR token = @token)

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
        THROW
    END CATCH
END
GO


-- =========================================
-- ROLES DE SQL SERVER
-- =========================================

CREATE ROLE rol_administrador;
CREATE ROLE rol_mesero;
CREATE ROLE rol_cocinero;
CREATE ROLE rol_cajero;
GO


-- =========================================
-- PERMISOS DE CADA ROL
-- =========================================

GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON SCHEMA::dbo TO rol_administrador;
GRANT BACKUP DATABASE TO rol_administrador;
-- Solo el administrador puede descifrar la cédula de los clientes
GRANT CONTROL ON CERTIFICATE::cert_datos_personales TO rol_administrador;
GRANT VIEW DEFINITION ON SYMMETRIC KEY::llave_datos_personales TO rol_administrador;

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
GRANT EXECUTE ON dbo.sp_turno_caja_insertar TO rol_cajero;
GRANT EXECUTE ON dbo.sp_cobrar_cuenta TO rol_cajero;
GRANT EXECUTE ON dbo.sp_anular_pedido TO rol_cajero;

GRANT SELECT ON dbo.vw_permisos_empleado TO rol_mesero, rol_cocinero, rol_cajero;
GRANT EXECUTE ON dbo.sp_sesion_validar TO rol_mesero, rol_cocinero, rol_cajero;
GRANT EXECUTE ON dbo.sp_sesion_cerrar TO rol_mesero, rol_cocinero, rol_cajero;

DENY UPDATE, DELETE ON dbo.AUDITORIA_PEDIDO TO rol_administrador;
DENY UPDATE, DELETE ON dbo.AUDITORIA_PEDIDO TO rol_mesero;
DENY UPDATE, DELETE ON dbo.AUDITORIA_PEDIDO TO rol_cocinero;
DENY UPDATE, DELETE ON dbo.AUDITORIA_PEDIDO TO rol_cajero;
GO


-- =========================================
-- USUARIOS DE LA APLICACION
-- =========================================

CREATE USER app_administrador WITHOUT LOGIN;
CREATE USER app_mesero WITHOUT LOGIN;
CREATE USER app_cocinero WITHOUT LOGIN;
CREATE USER app_cajero WITHOUT LOGIN;
GO


-- =========================================
-- MIEMBROS DE CADA ROL
-- =========================================

ALTER ROLE rol_administrador ADD MEMBER app_administrador;
ALTER ROLE rol_mesero ADD MEMBER app_mesero;
ALTER ROLE rol_cocinero ADD MEMBER app_cocinero;
ALTER ROLE rol_cajero ADD MEMBER app_cajero;
GO


-- =========================================
-- PROCEDIMIENTO sp_restaurar_restaurante (base master)
-- Lleva CREATE OR ALTER porque vive en master y no se borra junto con RestauranteDB
-- =========================================

USE master;
GO

CREATE OR ALTER PROCEDURE dbo.sp_restaurar_restaurante
    @base SYSNAME,
    @archivo NVARCHAR(460)
AS
BEGIN
    SET NOCOUNT ON

    DECLARE @sql NVARCHAR(MAX)
    DECLARE @bd NVARCHAR(260) = QUOTENAME(@base)

    -- RESTORE no puede ejecutarse dentro de una transacción explícita: el control es TRY/CATCH
    -- y, si algo falla, la base vuelve a MULTI_USER para no quedar bloqueada.
    BEGIN TRY
        IF @base IS NULL OR DB_ID(@base) IS NULL
            THROW 50040, 'La base de datos que se quiere restaurar no existe en este servidor.', 1

        IF @base IN (N'master', N'model', N'msdb', N'tempdb')
            THROW 50041, 'No se permite restaurar bases de datos del sistema.', 1

        IF NULLIF(LTRIM(RTRIM(@archivo)), N'') IS NULL
            THROW 50042, 'Debe indicar el archivo de respaldo.', 1

        RESTORE VERIFYONLY FROM DISK = @archivo WITH CHECKSUM

        SET @sql = N'ALTER DATABASE ' + @bd + N' SET SINGLE_USER WITH ROLLBACK IMMEDIATE;'
        EXEC sys.sp_executesql @sql

        RESTORE DATABASE @base FROM DISK = @archivo WITH REPLACE, CHECKSUM, RECOVERY

        SET @sql = N'ALTER DATABASE ' + @bd + N' SET MULTI_USER;'
        EXEC sys.sp_executesql @sql
    END TRY
    BEGIN CATCH
        IF DATABASEPROPERTYEX(@base, 'UserAccess') = 'SINGLE_USER'
        BEGIN
            BEGIN TRY
                SET @sql = N'ALTER DATABASE ' + @bd + N' SET MULTI_USER WITH ROLLBACK IMMEDIATE;'
                EXEC sys.sp_executesql @sql
            END TRY
            BEGIN CATCH
                SET @sql = NULL
            END CATCH
        END;

        THROW
    END CATCH
END
GO

USE RestauranteDB;
GO

PRINT N'Programación lista. Ahora ejecute 3_datos_iniciales.sql.';
GO
