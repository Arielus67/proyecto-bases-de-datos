USE RestauranteDB
GO

SET QUOTED_IDENTIFIER ON
SET ANSI_NULLS ON
GO

CREATE OR ALTER VIEW dbo.vw_menu_activo AS
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
LEFT JOIN dbo.PEDIDO p ON m.id_mesa = p.id_mesa AND p.estado IN ('ABIERTO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO')
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
WHERE dp.estado_cocina IN ('PENDIENTE', 'PREPARANDO')
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
INNER JOIN dbo.EMPLEADO emp ON f.id_empleado = emp.id_empleado
GO

CREATE OR ALTER TRIGGER dbo.trg_producto_baja_logica
ON dbo.PRODUCTO
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

CREATE OR ALTER TRIGGER dbo.trg_factura_no_eliminar
ON dbo.FACTURA
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

CREATE OR ALTER TRIGGER dbo.trg_pedido_auditoria
ON dbo.PEDIDO
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON

    BEGIN TRY
        DECLARE @actor INT = TRY_CAST(SESSION_CONTEXT(N'id_empleado') AS INT)
        DECLARE @usuario VARCHAR(100) = COALESCE(CAST(SESSION_CONTEXT(N'usuario_app') AS VARCHAR(100)), SUSER_SNAME())

        INSERT INTO dbo.AUDITORIA_PEDIDO (id_pedido, accion, usuario_bd, id_empleado_app, estado_nuevo, subtotal_nuevo)
        SELECT i.id_pedido, 'INSERT', @usuario, COALESCE(@actor, i.id_empleado), i.estado, i.subtotal
        FROM inserted i
        WHERE NOT EXISTS (SELECT 1 FROM deleted d WHERE d.id_pedido = i.id_pedido)

        INSERT INTO dbo.AUDITORIA_PEDIDO (id_pedido, accion, usuario_bd, id_empleado_app, estado_anterior, estado_nuevo, subtotal_anterior, subtotal_nuevo)
        SELECT i.id_pedido, 'UPDATE', @usuario, COALESCE(@actor, i.id_empleado), d.estado, i.estado, d.subtotal, i.subtotal
        FROM inserted i
        INNER JOIN deleted d ON i.id_pedido = d.id_pedido

        INSERT INTO dbo.AUDITORIA_PEDIDO (id_pedido, accion, usuario_bd, id_empleado_app, estado_anterior, subtotal_anterior)
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

CREATE OR ALTER TRIGGER dbo.trg_pedido_estado_final
ON dbo.PEDIDO
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

CREATE OR ALTER TRIGGER dbo.trg_insumo_stock
ON dbo.INSUMO
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

CREATE OR ALTER TRIGGER dbo.trg_factura_inmutable
ON dbo.FACTURA
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

CREATE OR ALTER TRIGGER dbo.trg_pago_inmutable
ON dbo.PAGO
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

CREATE OR ALTER TRIGGER dbo.trg_turno_caja_cerrado
ON dbo.TURNO_CAJA
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

CREATE OR ALTER FUNCTION dbo.fn_tiene_permiso (@id_empleado INT, @clave VARCHAR(60))
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

CREATE OR ALTER VIEW dbo.vw_permisos_empleado AS
SELECT e.id_empleado, p.clave
FROM dbo.EMPLEADO e
INNER JOIN dbo.ROL_PERMISO rp ON rp.id_rol = e.id_rol
INNER JOIN dbo.PERMISO p ON p.id_permiso = rp.id_permiso
WHERE e.activo = 1
GO

CREATE OR ALTER PROCEDURE dbo.sp_rol_reemplazar_permisos
    @id_rol INT,
    @permisos NVARCHAR(MAX)
AS
BEGIN
    SET NOCOUNT ON

    DECLARE @claves TABLE (clave VARCHAR(60) PRIMARY KEY)
    DECLARE @desconocido VARCHAR(60), @mensaje NVARCHAR(200)

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

    DELETE FROM dbo.ROL_PERMISO WHERE id_rol = @id_rol

    INSERT INTO dbo.ROL_PERMISO (id_rol, id_permiso)
    SELECT @id_rol, p.id_permiso
    FROM dbo.PERMISO p
    INNER JOIN @claves c ON c.clave = p.clave

    IF NOT EXISTS (SELECT 1 FROM dbo.EMPLEADO e WHERE dbo.fn_tiene_permiso(e.id_empleado, 'roles.editar') = 1)
        THROW 50105, 'Debe quedar al menos un empleado activo con permiso para editar roles.', 1
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_rol_insertar
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

CREATE OR ALTER PROCEDURE dbo.sp_rol_actualizar
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

CREATE OR ALTER PROCEDURE dbo.sp_rol_eliminar
    @id_rol INT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.ROL WHERE id_rol = @id_rol)
            THROW 50101, 'El rol no existe.', 1

        DELETE FROM dbo.ROL WHERE id_rol = @id_rol
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar el rol: tiene empleados asociados.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_rol_consultar
    @id_rol INT = NULL
AS
BEGIN
    SET NOCOUNT ON

    IF @id_rol IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.ROL WHERE id_rol = @id_rol)
        THROW 50101, 'El rol no existe.', 1

    SELECT r.id_rol, r.nombre_rol, p.clave AS permiso
    FROM dbo.ROL r
    LEFT JOIN dbo.ROL_PERMISO rp ON rp.id_rol = r.id_rol
    LEFT JOIN dbo.PERMISO p ON p.id_permiso = rp.id_permiso
    WHERE @id_rol IS NULL OR r.id_rol = @id_rol
    ORDER BY r.nombre_rol, p.clave
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_estacion_insertar
    @nombre_estacion VARCHAR(50),
    @id_estacion INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NULLIF(LTRIM(RTRIM(@nombre_estacion)), '') IS NULL
            THROW 50100, 'El nombre de la estación es obligatorio.', 1

        INSERT INTO dbo.ESTACION (nombre_estacion)
        VALUES (LTRIM(RTRIM(@nombre_estacion)))

        SET @id_estacion = SCOPE_IDENTITY()
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe una estación con ese nombre.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos de la estación no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_estacion_actualizar
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

        UPDATE dbo.ESTACION
        SET nombre_estacion = LTRIM(RTRIM(@nombre_estacion))
        WHERE id_estacion = @id_estacion
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe una estación con ese nombre.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos de la estación no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_estacion_eliminar
    @id_estacion INT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.ESTACION WHERE id_estacion = @id_estacion)
            THROW 50101, 'La estación no existe.', 1

        DELETE FROM dbo.ESTACION WHERE id_estacion = @id_estacion
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar la estación: tiene productos o cocineros asociados.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_estacion_consultar
    @id_estacion INT = NULL
AS
BEGIN
    SET NOCOUNT ON

    IF @id_estacion IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.ESTACION WHERE id_estacion = @id_estacion)
        THROW 50101, 'La estación no existe.', 1

    SELECT id_estacion, nombre_estacion
    FROM dbo.ESTACION
    WHERE @id_estacion IS NULL OR id_estacion = @id_estacion
    ORDER BY nombre_estacion
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_categoria_insertar
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

        INSERT INTO dbo.CATEGORIA (nombre_categoria, descripcion)
        VALUES (LTRIM(RTRIM(@nombre_categoria)), NULLIF(LTRIM(RTRIM(@descripcion)), ''))

        SET @id_categoria = SCOPE_IDENTITY()
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe una categoría con ese nombre.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos de la categoría no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_categoria_actualizar
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

        UPDATE dbo.CATEGORIA
        SET nombre_categoria = LTRIM(RTRIM(@nombre_categoria)),
            descripcion = NULLIF(LTRIM(RTRIM(@descripcion)), '')
        WHERE id_categoria = @id_categoria
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe una categoría con ese nombre.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos de la categoría no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_categoria_eliminar
    @id_categoria INT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.CATEGORIA WHERE id_categoria = @id_categoria)
            THROW 50101, 'La categoría no existe.', 1

        DELETE FROM dbo.CATEGORIA WHERE id_categoria = @id_categoria
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar la categoría: tiene productos asociados.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_categoria_consultar
    @id_categoria INT = NULL
AS
BEGIN
    SET NOCOUNT ON

    IF @id_categoria IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.CATEGORIA WHERE id_categoria = @id_categoria)
        THROW 50101, 'La categoría no existe.', 1

    SELECT id_categoria, nombre_categoria, descripcion
    FROM dbo.CATEGORIA
    WHERE @id_categoria IS NULL OR id_categoria = @id_categoria
    ORDER BY nombre_categoria
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_metodo_pago_insertar
    @nombre_metodo VARCHAR(50),
    @id_metodo_pago INT OUTPUT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NULLIF(LTRIM(RTRIM(@nombre_metodo)), '') IS NULL
            THROW 50100, 'El nombre del método de pago es obligatorio.', 1

        INSERT INTO dbo.METODO_PAGO (nombre_metodo)
        VALUES (LTRIM(RTRIM(@nombre_metodo)))

        SET @id_metodo_pago = SCOPE_IDENTITY()
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un método de pago con ese nombre.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del método de pago no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_metodo_pago_actualizar
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

        UPDATE dbo.METODO_PAGO
        SET nombre_metodo = LTRIM(RTRIM(@nombre_metodo))
        WHERE id_metodo_pago = @id_metodo_pago
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un método de pago con ese nombre.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del método de pago no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_metodo_pago_eliminar
    @id_metodo_pago INT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.METODO_PAGO WHERE id_metodo_pago = @id_metodo_pago)
            THROW 50101, 'El método de pago no existe.', 1

        DELETE FROM dbo.METODO_PAGO WHERE id_metodo_pago = @id_metodo_pago
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar el método de pago: tiene pagos asociados.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_metodo_pago_consultar
    @id_metodo_pago INT = NULL
AS
BEGIN
    SET NOCOUNT ON

    IF @id_metodo_pago IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.METODO_PAGO WHERE id_metodo_pago = @id_metodo_pago)
        THROW 50101, 'El método de pago no existe.', 1

    SELECT id_metodo_pago, nombre_metodo
    FROM dbo.METODO_PAGO
    WHERE @id_metodo_pago IS NULL OR id_metodo_pago = @id_metodo_pago
    ORDER BY nombre_metodo
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_mesa_insertar
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

        INSERT INTO dbo.MESA (numero_mesa, capacidad, zona, estado)
        VALUES (@numero_mesa, @capacidad, LTRIM(RTRIM(@zona)), LTRIM(RTRIM(@estado)))

        SET @id_mesa = SCOPE_IDENTITY()
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe una mesa con ese número.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos de la mesa no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_mesa_actualizar
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

        UPDATE dbo.MESA
        SET numero_mesa = @numero_mesa,
            capacidad = @capacidad,
            zona = LTRIM(RTRIM(@zona)),
            estado = LTRIM(RTRIM(@estado))
        WHERE id_mesa = @id_mesa
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe una mesa con ese número.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos de la mesa no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_mesa_eliminar
    @id_mesa INT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.MESA WHERE id_mesa = @id_mesa)
            THROW 50101, 'La mesa no existe.', 1

        DELETE FROM dbo.MESA WHERE id_mesa = @id_mesa
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar la mesa: tiene pedidos asociados. Puede ponerla en mantenimiento.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_mesa_consultar
    @id_mesa INT = NULL
AS
BEGIN
    SET NOCOUNT ON

    IF @id_mesa IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.MESA WHERE id_mesa = @id_mesa)
        THROW 50101, 'La mesa no existe.', 1

    SELECT id_mesa, numero_mesa, capacidad, zona, estado
    FROM dbo.MESA
    WHERE @id_mesa IS NULL OR id_mesa = @id_mesa
    ORDER BY numero_mesa
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_cliente_insertar
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
        IF NULLIF(LTRIM(RTRIM(@cedula)), '') IS NULL
            THROW 50100, 'La cédula del cliente es obligatoria.', 1
        IF NULLIF(LTRIM(RTRIM(@nombre)), '') IS NULL OR NULLIF(LTRIM(RTRIM(@apellido)), '') IS NULL
            THROW 50100, 'Nombre y apellido del cliente son obligatorios.', 1

        INSERT INTO dbo.CLIENTE (cedula, nombre, apellido, telefono, correo)
        VALUES (LTRIM(RTRIM(@cedula)), LTRIM(RTRIM(@nombre)), LTRIM(RTRIM(@apellido)), NULLIF(LTRIM(RTRIM(@telefono)), ''), NULLIF(LTRIM(RTRIM(@correo)), ''))

        SET @id_cliente = SCOPE_IDENTITY()
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un cliente con esa cédula.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del cliente no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_cliente_actualizar
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
        IF NULLIF(LTRIM(RTRIM(@cedula)), '') IS NULL
            THROW 50100, 'La cédula del cliente es obligatoria.', 1
        IF NULLIF(LTRIM(RTRIM(@nombre)), '') IS NULL OR NULLIF(LTRIM(RTRIM(@apellido)), '') IS NULL
            THROW 50100, 'Nombre y apellido del cliente son obligatorios.', 1

        UPDATE dbo.CLIENTE
        SET cedula = LTRIM(RTRIM(@cedula)),
            nombre = LTRIM(RTRIM(@nombre)),
            apellido = LTRIM(RTRIM(@apellido)),
            telefono = NULLIF(LTRIM(RTRIM(@telefono)), ''),
            correo = NULLIF(LTRIM(RTRIM(@correo)), '')
        WHERE id_cliente = @id_cliente
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un cliente con esa cédula.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del cliente no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_cliente_eliminar
    @id_cliente INT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.CLIENTE WHERE id_cliente = @id_cliente)
            THROW 50101, 'El cliente no existe.', 1

        DELETE FROM dbo.CLIENTE WHERE id_cliente = @id_cliente
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar el cliente: tiene facturas asociadas.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_cliente_consultar
    @id_cliente INT = NULL
AS
BEGIN
    SET NOCOUNT ON

    IF @id_cliente IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.CLIENTE WHERE id_cliente = @id_cliente)
        THROW 50101, 'El cliente no existe.', 1

    SELECT id_cliente, cedula, nombre, apellido, telefono, correo
    FROM dbo.CLIENTE
    WHERE @id_cliente IS NULL OR id_cliente = @id_cliente
    ORDER BY apellido, nombre
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_producto_insertar
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

        INSERT INTO dbo.PRODUCTO (nombre_producto, descripcion, precio, id_categoria, id_estacion, activo, foto)
        VALUES (LTRIM(RTRIM(@nombre_producto)), NULLIF(LTRIM(RTRIM(@descripcion)), ''), @precio, @id_categoria, @id_estacion, @activo, NULLIF(LTRIM(RTRIM(@foto)), ''))

        SET @id_producto = SCOPE_IDENTITY()
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un producto con ese nombre.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del producto no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_producto_actualizar
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

        UPDATE dbo.PRODUCTO
        SET nombre_producto = LTRIM(RTRIM(@nombre_producto)),
            descripcion = NULLIF(LTRIM(RTRIM(@descripcion)), ''),
            precio = @precio,
            id_categoria = @id_categoria,
            id_estacion = @id_estacion,
            activo = @activo,
            foto = NULLIF(LTRIM(RTRIM(@foto)), '')
        WHERE id_producto = @id_producto
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un producto con ese nombre.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del producto no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_producto_eliminar
    @id_producto INT,
    @desactivado BIT OUTPUT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.PRODUCTO WHERE id_producto = @id_producto)
            THROW 50101, 'El producto no existe.', 1

        DELETE FROM dbo.PRODUCTO WHERE id_producto = @id_producto

        SET @desactivado = CASE WHEN EXISTS (SELECT 1 FROM dbo.PRODUCTO WHERE id_producto = @id_producto) THEN 1 ELSE 0 END
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar el producto: tiene registros relacionados.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_producto_consultar
    @id_producto INT = NULL
AS
BEGIN
    SET NOCOUNT ON

    IF @id_producto IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.PRODUCTO WHERE id_producto = @id_producto)
        THROW 50101, 'El producto no existe.', 1

    SELECT id_producto, nombre_producto, descripcion, precio, id_categoria, id_estacion, activo, foto
    FROM dbo.PRODUCTO
    WHERE @id_producto IS NULL OR id_producto = @id_producto
    ORDER BY nombre_producto
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_insumo_insertar
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

        INSERT INTO dbo.INSUMO (nombre_insumo, unidad_medida, stock_actual, stock_minimo, costo_unitario)
        VALUES (LTRIM(RTRIM(@nombre_insumo)), LTRIM(RTRIM(@unidad_medida)), @stock_actual, @stock_minimo, @costo_unitario)

        SET @id_insumo = SCOPE_IDENTITY()
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un insumo con ese nombre.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del insumo no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
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
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.INSUMO WHERE id_insumo = @id_insumo)
            THROW 50101, 'El insumo no existe.', 1
        IF NULLIF(LTRIM(RTRIM(@nombre_insumo)), '') IS NULL OR NULLIF(LTRIM(RTRIM(@unidad_medida)), '') IS NULL
            THROW 50100, 'Nombre y unidad de medida del insumo son obligatorios.', 1
        IF @stock_actual IS NULL OR @stock_actual < 0 OR @stock_minimo IS NULL OR @stock_minimo < 0 OR @costo_unitario IS NULL OR @costo_unitario < 0
            THROW 50100, 'Stock y costo no pueden ser negativos.', 1

        UPDATE dbo.INSUMO
        SET nombre_insumo = LTRIM(RTRIM(@nombre_insumo)),
            unidad_medida = LTRIM(RTRIM(@unidad_medida)),
            stock_actual = @stock_actual,
            stock_minimo = @stock_minimo,
            costo_unitario = @costo_unitario
        WHERE id_insumo = @id_insumo
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un insumo con ese nombre.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del insumo no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_insumo_eliminar
    @id_insumo INT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.INSUMO WHERE id_insumo = @id_insumo)
            THROW 50101, 'El insumo no existe.', 1

        DELETE FROM dbo.INSUMO WHERE id_insumo = @id_insumo
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar el insumo: se usa en recetas o tiene alertas de stock.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_insumo_consultar
    @id_insumo INT = NULL
AS
BEGIN
    SET NOCOUNT ON

    IF @id_insumo IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.INSUMO WHERE id_insumo = @id_insumo)
        THROW 50101, 'El insumo no existe.', 1

    SELECT id_insumo, nombre_insumo, unidad_medida, stock_actual, stock_minimo, costo_unitario
    FROM dbo.INSUMO
    WHERE @id_insumo IS NULL OR id_insumo = @id_insumo
    ORDER BY nombre_insumo
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_receta_insertar
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

        INSERT INTO dbo.RECETA (id_producto, id_insumo, cantidad)
        VALUES (@id_producto, @id_insumo, @cantidad)
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ese insumo ya está en la receta del producto.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos de la receta no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_receta_actualizar
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

        UPDATE dbo.RECETA
        SET cantidad = @cantidad
        WHERE id_producto = @id_producto AND id_insumo = @id_insumo
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ese insumo ya está en la receta del producto.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos de la receta no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_receta_eliminar
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

        DELETE FROM dbo.RECETA
        WHERE id_producto = @id_producto AND (@id_insumo IS NULL OR id_insumo = @id_insumo)
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar la receta.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_receta_consultar
    @id_producto INT = NULL
AS
BEGIN
    SET NOCOUNT ON

    IF @id_producto IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.PRODUCTO WHERE id_producto = @id_producto)
        THROW 50101, 'El producto indicado no existe.', 1

    SELECT r.id_producto, p.nombre_producto, r.id_insumo, i.nombre_insumo, i.unidad_medida, r.cantidad
    FROM dbo.RECETA r
    INNER JOIN dbo.PRODUCTO p ON p.id_producto = r.id_producto
    INNER JOIN dbo.INSUMO i ON i.id_insumo = r.id_insumo
    WHERE @id_producto IS NULL OR r.id_producto = @id_producto
    ORDER BY p.nombre_producto, i.nombre_insumo
END
GO

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

        INSERT INTO dbo.EMPLEADO (nombre, apellido, correo, contrasena_hash, id_rol, activo)
        VALUES (LTRIM(RTRIM(@nombre)), LTRIM(RTRIM(@apellido)), LTRIM(RTRIM(@correo)), LTRIM(RTRIM(@contrasena_hash)), @id_rol, @activo)

        SET @id_empleado = SCOPE_IDENTITY()
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un empleado con ese correo.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del empleado no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
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

        UPDATE dbo.EMPLEADO
        SET nombre = LTRIM(RTRIM(@nombre)),
            apellido = LTRIM(RTRIM(@apellido)),
            correo = LTRIM(RTRIM(@correo)),
            contrasena_hash = COALESCE(NULLIF(LTRIM(RTRIM(@contrasena_hash)), ''), contrasena_hash),
            id_rol = @id_rol,
            activo = @activo
        WHERE id_empleado = @id_empleado
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ya existe un empleado con ese correo.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del empleado no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_empleado_eliminar
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

        DELETE FROM dbo.EMPLEADO WHERE id_empleado = @id_empleado
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar el empleado: tiene pedidos, turnos o facturas asociados. Puede desactivarlo.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_empleado_consultar
    @id_empleado INT = NULL
AS
BEGIN
    SET NOCOUNT ON

    IF @id_empleado IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.EMPLEADO WHERE id_empleado = @id_empleado)
        THROW 50101, 'El empleado no existe.', 1

    SELECT id_empleado, nombre, apellido, correo, id_rol, fecha_ingreso, activo
    FROM dbo.EMPLEADO
    WHERE @id_empleado IS NULL OR id_empleado = @id_empleado
    ORDER BY apellido, nombre
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_turno_caja_insertar
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

        INSERT INTO dbo.TURNO_CAJA (id_empleado, monto_inicial)
        VALUES (@id_empleado, @monto_inicial)

        SET @id_turno = SCOPE_IDENTITY()
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ese cajero ya tiene un turno abierto.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del turno no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_turno_caja_actualizar
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

        UPDATE dbo.TURNO_CAJA
        SET id_empleado = @id_empleado,
            monto_inicial = @monto_inicial,
            fecha_cierre = @fecha_cierre,
            monto_final = @monto_final
        WHERE id_turno = @id_turno
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() IN (2601, 2627)
            THROW 50103, 'Ese cajero ya tiene un turno abierto.', 1
        IF ERROR_NUMBER() = 547
            THROW 50102, 'Los datos del turno no cumplen las reglas de la tabla.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_turno_caja_eliminar
    @id_turno INT
AS
BEGIN
    SET NOCOUNT ON
    SET XACT_ABORT ON

    BEGIN TRY
        IF NOT EXISTS (SELECT 1 FROM dbo.TURNO_CAJA WHERE id_turno = @id_turno)
            THROW 50101, 'El turno de caja no existe.', 1

        DELETE FROM dbo.TURNO_CAJA WHERE id_turno = @id_turno
    END TRY
    BEGIN CATCH
        IF ERROR_NUMBER() = 547
            THROW 50102, 'No se puede eliminar el turno: tiene facturas asociadas.', 1;
        THROW
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_turno_caja_consultar
    @id_turno INT = NULL
AS
BEGIN
    SET NOCOUNT ON

    IF @id_turno IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.TURNO_CAJA WHERE id_turno = @id_turno)
        THROW 50101, 'El turno de caja no existe.', 1

    SELECT id_turno, id_empleado, monto_inicial, fecha_cierre, monto_final
    FROM dbo.TURNO_CAJA
    WHERE @id_turno IS NULL OR id_turno = @id_turno
    ORDER BY fecha_apertura DESC
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_registrar_pedido
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

CREATE OR ALTER PROCEDURE dbo.sp_agregar_items_pedido
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

CREATE OR ALTER PROCEDURE dbo.sp_cobrar_cuenta
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

CREATE OR ALTER PROCEDURE dbo.sp_anular_pedido
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

CREATE OR ALTER PROCEDURE dbo.sp_reporte_ventas
    @fecha_inicio DATETIME,
    @fecha_fin DATETIME,
    @agrupar_por VARCHAR(20)
AS
BEGIN
    SET NOCOUNT ON

    BEGIN TRY
        IF @fecha_inicio IS NULL OR @fecha_fin IS NULL OR @fecha_fin < @fecha_inicio
            THROW 50056, 'El rango de fechas del reporte no es válido.', 1

        IF @agrupar_por NOT IN ('DIA', 'PRODUCTO', 'MESERO', 'PAGO')
            THROW 50057, 'Agrupación no válida. Use DIA, PRODUCTO, MESERO o PAGO.', 1

        SET TRANSACTION ISOLATION LEVEL SNAPSHOT

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

CREATE OR ALTER PROCEDURE dbo.sp_respaldar_bd
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

CREATE OR ALTER PROCEDURE dbo.sp_verificar_respaldo
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

CREATE OR ALTER PROCEDURE dbo.sp_sesion_abrir
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

CREATE OR ALTER PROCEDURE dbo.sp_sesion_validar
    @id_empleado INT,
    @token CHAR(64),
    @vigente BIT OUTPUT
AS
BEGIN
    SET NOCOUNT ON

    SET @vigente = CASE WHEN EXISTS (
        SELECT 1 FROM dbo.SESION_ACTIVA WHERE id_empleado = @id_empleado AND token = @token
    ) THEN 1 ELSE 0 END

    IF @vigente = 1
        UPDATE dbo.SESION_ACTIVA
        SET ultima_actividad = GETDATE()
        WHERE id_empleado = @id_empleado
          AND token = @token
          AND ultima_actividad < DATEADD(SECOND, -30, GETDATE())
END
GO

CREATE OR ALTER PROCEDURE dbo.sp_sesion_cerrar
    @id_empleado INT,
    @token CHAR(64) = NULL
AS
BEGIN
    SET NOCOUNT ON

    DELETE FROM dbo.SESION_ACTIVA
    WHERE id_empleado = @id_empleado
      AND (@token IS NULL OR token = @token)
END
GO

IF DATABASE_PRINCIPAL_ID(N'rol_administrador') IS NULL CREATE ROLE rol_administrador
IF DATABASE_PRINCIPAL_ID(N'rol_mesero') IS NULL CREATE ROLE rol_mesero
IF DATABASE_PRINCIPAL_ID(N'rol_cocinero') IS NULL CREATE ROLE rol_cocinero
IF DATABASE_PRINCIPAL_ID(N'rol_cajero') IS NULL CREATE ROLE rol_cajero
GO

GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON SCHEMA::dbo TO rol_administrador
GRANT BACKUP DATABASE TO rol_administrador

GRANT SELECT ON dbo.MESA TO rol_mesero
GRANT SELECT ON dbo.PRODUCTO TO rol_mesero
GRANT SELECT ON dbo.CATEGORIA TO rol_mesero
GRANT SELECT ON dbo.PEDIDO TO rol_mesero
GRANT SELECT ON dbo.DETALLE_PEDIDO TO rol_mesero
GRANT SELECT ON dbo.vw_menu_activo TO rol_mesero
GRANT SELECT ON dbo.vw_mesas_estado TO rol_mesero
GRANT UPDATE (estado) ON dbo.PEDIDO TO rol_mesero
GRANT UPDATE (estado_cocina, fecha_actualizacion) ON dbo.DETALLE_PEDIDO TO rol_mesero
GRANT EXECUTE ON dbo.sp_registrar_pedido TO rol_mesero
GRANT EXECUTE ON dbo.sp_agregar_items_pedido TO rol_mesero

GRANT SELECT ON dbo.PEDIDO TO rol_cocinero
GRANT SELECT ON dbo.DETALLE_PEDIDO TO rol_cocinero
GRANT SELECT ON dbo.PRODUCTO TO rol_cocinero
GRANT SELECT ON dbo.MESA TO rol_cocinero
GRANT SELECT ON dbo.vw_cola_cocina TO rol_cocinero
GRANT UPDATE (estado) ON dbo.PEDIDO TO rol_cocinero
GRANT UPDATE (estado_cocina, fecha_actualizacion) ON dbo.DETALLE_PEDIDO TO rol_cocinero

GRANT SELECT ON dbo.PEDIDO TO rol_cajero
GRANT SELECT ON dbo.DETALLE_PEDIDO TO rol_cajero
GRANT SELECT ON dbo.PRODUCTO TO rol_cajero
GRANT SELECT ON dbo.MESA TO rol_cajero
GRANT SELECT ON dbo.METODO_PAGO TO rol_cajero
GRANT SELECT ON dbo.FACTURA TO rol_cajero
GRANT SELECT ON dbo.PAGO TO rol_cajero
GRANT SELECT ON dbo.TURNO_CAJA TO rol_cajero
GRANT SELECT ON dbo.CAJERO TO rol_cajero
GRANT SELECT (id_cliente, nombre, apellido) ON dbo.CLIENTE TO rol_cajero
GRANT SELECT (id_empleado, nombre, apellido) ON dbo.EMPLEADO TO rol_cajero
GRANT SELECT ON dbo.vw_mesas_estado TO rol_cajero
GRANT SELECT ON dbo.vw_factura_ticket TO rol_cajero
GRANT UPDATE (fecha_cierre, monto_final) ON dbo.TURNO_CAJA TO rol_cajero
GRANT EXECUTE ON dbo.sp_turno_caja_insertar TO rol_cajero
GRANT EXECUTE ON dbo.sp_cobrar_cuenta TO rol_cajero
GRANT EXECUTE ON dbo.sp_anular_pedido TO rol_cajero

GRANT SELECT ON dbo.vw_permisos_empleado TO rol_mesero, rol_cocinero, rol_cajero
GRANT EXECUTE ON dbo.sp_sesion_validar TO rol_mesero, rol_cocinero, rol_cajero
GRANT EXECUTE ON dbo.sp_sesion_cerrar TO rol_mesero, rol_cocinero, rol_cajero

DENY UPDATE, DELETE ON dbo.AUDITORIA_PEDIDO TO rol_administrador
DENY UPDATE, DELETE ON dbo.AUDITORIA_PEDIDO TO rol_mesero
DENY UPDATE, DELETE ON dbo.AUDITORIA_PEDIDO TO rol_cocinero
DENY UPDATE, DELETE ON dbo.AUDITORIA_PEDIDO TO rol_cajero
GO

IF DATABASE_PRINCIPAL_ID(N'app_administrador') IS NULL CREATE USER app_administrador WITHOUT LOGIN
IF DATABASE_PRINCIPAL_ID(N'app_mesero') IS NULL CREATE USER app_mesero WITHOUT LOGIN
IF DATABASE_PRINCIPAL_ID(N'app_cocinero') IS NULL CREATE USER app_cocinero WITHOUT LOGIN
IF DATABASE_PRINCIPAL_ID(N'app_cajero') IS NULL CREATE USER app_cajero WITHOUT LOGIN
GO

IF IS_ROLEMEMBER(N'rol_administrador', N'app_administrador') = 0 ALTER ROLE rol_administrador ADD MEMBER app_administrador
IF IS_ROLEMEMBER(N'rol_mesero', N'app_mesero') = 0 ALTER ROLE rol_mesero ADD MEMBER app_mesero
IF IS_ROLEMEMBER(N'rol_cocinero', N'app_cocinero') = 0 ALTER ROLE rol_cocinero ADD MEMBER app_cocinero
IF IS_ROLEMEMBER(N'rol_cajero', N'app_cajero') = 0 ALTER ROLE rol_cajero ADD MEMBER app_cajero
GO

USE master
GO

CREATE OR ALTER PROCEDURE dbo.sp_restaurar_restaurante
    @base SYSNAME,
    @archivo NVARCHAR(460)
AS
BEGIN
    SET NOCOUNT ON

    DECLARE @sql NVARCHAR(MAX)
    DECLARE @bd NVARCHAR(260) = QUOTENAME(@base)

    IF @base IS NULL OR DB_ID(@base) IS NULL
        THROW 50040, 'La base de datos que se quiere restaurar no existe en este servidor.', 1

    IF @base IN (N'master', N'model', N'msdb', N'tempdb')
        THROW 50041, 'No se permite restaurar bases de datos del sistema.', 1

    IF NULLIF(LTRIM(RTRIM(@archivo)), N'') IS NULL
        THROW 50042, 'Debe indicar el archivo de respaldo.', 1

    RESTORE VERIFYONLY FROM DISK = @archivo WITH CHECKSUM

    BEGIN TRY
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

USE RestauranteDB
GO

PRINT N'Programación lista. Si la base está vacía, ejecute ahora 3_datos_iniciales.sql.'
GO
