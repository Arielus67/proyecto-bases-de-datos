USE RestauranteDB;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

BEGIN TRY
    BEGIN TRANSACTION;

    INSERT INTO dbo.ROL (nombre_rol)
    SELECT v.nombre FROM (VALUES ('Administrador'), ('Mesero'), ('Cocinero'), ('Cajero')) v(nombre)
    WHERE NOT EXISTS (SELECT 1 FROM dbo.ROL r WHERE r.nombre_rol LIKE '%' + LEFT(v.nombre, 5) + '%');

    INSERT INTO dbo.ESTACION (nombre_estacion)
    SELECT v.nombre FROM (VALUES ('Cocina caliente'), ('Bebidas'), ('Repostería')) v(nombre)
    WHERE NOT EXISTS (SELECT 1 FROM dbo.ESTACION e WHERE e.nombre_estacion = v.nombre);

    INSERT INTO dbo.CATEGORIA (nombre_categoria, descripcion)
    SELECT v.nombre, v.descripcion FROM (VALUES
        ('Platos fuertes', 'Platos principales de la casa'),
        ('Entradas', 'Para compartir'),
        ('Bebidas', 'Naturales y calientes'),
        ('Postres', 'Dulces de la casa')
    ) v(nombre, descripcion)
    WHERE NOT EXISTS (SELECT 1 FROM dbo.CATEGORIA c WHERE c.nombre_categoria = v.nombre);

    INSERT INTO dbo.METODO_PAGO (nombre_metodo)
    SELECT v.nombre FROM (VALUES ('Efectivo'), ('Tarjeta'), ('SINPE Móvil')) v(nombre)
    WHERE NOT EXISTS (SELECT 1 FROM dbo.METODO_PAGO m WHERE m.nombre_metodo = v.nombre);

    INSERT INTO dbo.MESA (numero_mesa, capacidad, zona, estado)
    SELECT v.numero, v.capacidad, v.zona, 'DISPONIBLE' FROM (VALUES
        (1, 2, 'Salón'), (2, 4, 'Salón'), (3, 4, 'Salón'), (4, 6, 'Salón'),
        (5, 2, 'Terraza'), (6, 4, 'Terraza'), (7, 4, 'Terraza'), (8, 8, 'Terraza')
    ) v(numero, capacidad, zona)
    WHERE NOT EXISTS (SELECT 1 FROM dbo.MESA m WHERE m.numero_mesa = v.numero);

    INSERT INTO dbo.INSUMO (nombre_insumo, unidad_medida, stock_actual, stock_minimo, costo_unitario)
    SELECT v.nombre, v.unidad, v.stock, v.minimo, v.costo FROM (VALUES
        ('Arroz', 'kg', 25.00, 5.00, 900.00),
        ('Frijoles', 'kg', 15.00, 3.00, 1400.00),
        ('Pollo', 'kg', 12.00, 3.00, 3200.00),
        ('Carne de res', 'kg', 10.00, 2.00, 5500.00),
        ('Plátano maduro', 'unidad', 40.00, 10.00, 250.00),
        ('Huevo', 'unidad', 60.00, 12.00, 120.00),
        ('Café molido', 'kg', 3.00, 0.50, 6000.00),
        ('Leche', 'L', 12.00, 3.00, 900.00),
        ('Fruta natural', 'kg', 8.00, 2.00, 1500.00),
        ('Azúcar', 'kg', 10.00, 2.00, 800.00)
    ) v(nombre, unidad, stock, minimo, costo)
    WHERE NOT EXISTS (SELECT 1 FROM dbo.INSUMO i WHERE i.nombre_insumo = v.nombre);

    INSERT INTO dbo.PRODUCTO (nombre_producto, descripcion, precio, id_categoria, id_estacion, activo)
    SELECT v.nombre, v.descripcion, v.precio, c.id_categoria, e.id_estacion, 1
    FROM (VALUES
        ('Casado con pollo', 'Arroz, frijoles, plátano maduro y pollo', 4500.00, 'Platos fuertes', 'Cocina caliente'),
        ('Casado con bistec', 'Arroz, frijoles, plátano maduro y bistec', 5200.00, 'Platos fuertes', 'Cocina caliente'),
        ('Arroz con pollo', 'Arroz amarillo con pollo desmenuzado', 4200.00, 'Platos fuertes', 'Cocina caliente'),
        ('Gallo pinto con huevo', 'Gallo pinto, huevo frito y plátano', 3200.00, 'Entradas', 'Cocina caliente'),
        ('Patacones', 'Patacones con frijoles molidos', 2800.00, 'Entradas', 'Cocina caliente'),
        ('Refresco natural', 'Fruta de temporada', 1500.00, 'Bebidas', 'Bebidas'),
        ('Café chorreado', 'Café de la casa', 1200.00, 'Bebidas', 'Bebidas'),
        ('Tres leches', 'Queque tres leches', 2500.00, 'Postres', 'Repostería')
    ) v(nombre, descripcion, precio, categoria, estacion)
    INNER JOIN dbo.CATEGORIA c ON c.nombre_categoria = v.categoria
    INNER JOIN dbo.ESTACION e ON e.nombre_estacion = v.estacion
    WHERE NOT EXISTS (SELECT 1 FROM dbo.PRODUCTO p WHERE p.nombre_producto = v.nombre);

    INSERT INTO dbo.RECETA (id_producto, id_insumo, cantidad)
    SELECT p.id_producto, i.id_insumo, v.cantidad
    FROM (VALUES
        ('Casado con pollo', 'Arroz', 0.20), ('Casado con pollo', 'Frijoles', 0.15), ('Casado con pollo', 'Plátano maduro', 1.00), ('Casado con pollo', 'Pollo', 0.25),
        ('Casado con bistec', 'Arroz', 0.20), ('Casado con bistec', 'Frijoles', 0.15), ('Casado con bistec', 'Plátano maduro', 1.00), ('Casado con bistec', 'Carne de res', 0.25),
        ('Arroz con pollo', 'Arroz', 0.25), ('Arroz con pollo', 'Pollo', 0.20),
        ('Gallo pinto con huevo', 'Arroz', 0.15), ('Gallo pinto con huevo', 'Frijoles', 0.10), ('Gallo pinto con huevo', 'Huevo', 1.00), ('Gallo pinto con huevo', 'Plátano maduro', 1.00),
        ('Patacones', 'Plátano maduro', 2.00), ('Patacones', 'Frijoles', 0.10),
        ('Refresco natural', 'Fruta natural', 0.20), ('Refresco natural', 'Azúcar', 0.03),
        ('Café chorreado', 'Café molido', 0.02), ('Café chorreado', 'Azúcar', 0.01),
        ('Tres leches', 'Leche', 0.15), ('Tres leches', 'Huevo', 1.00), ('Tres leches', 'Azúcar', 0.05)
    ) v(producto, insumo, cantidad)
    INNER JOIN dbo.PRODUCTO p ON p.nombre_producto = v.producto
    INNER JOIN dbo.INSUMO i ON i.nombre_insumo = v.insumo
    WHERE NOT EXISTS (SELECT 1 FROM dbo.RECETA r WHERE r.id_producto = p.id_producto AND r.id_insumo = i.id_insumo);

    DECLARE @hash VARCHAR(255) = '$2y$12$8dZziRsu0nf02qrdnPihOeQBzK0G4JKFE6ZZpcX5oEsH.Z6r7zKjW';

    INSERT INTO dbo.EMPLEADO (nombre, apellido, correo, contrasena_hash, id_rol, activo)
    SELECT v.nombre, 'Prueba', v.correo, @hash,
           (SELECT TOP (1) r.id_rol FROM dbo.ROL r WHERE r.nombre_rol LIKE '%' + v.rol + '%' ORDER BY r.id_rol), 1
    FROM (VALUES
        ('Admin', 'admin@restaurante.cr', 'admin'),
        ('Mesero', 'mesero@restaurante.cr', 'mesero'),
        ('Cocinero', 'cocina@restaurante.cr', 'cocinero'),
        ('Cajero', 'cajero@restaurante.cr', 'cajero')
    ) v(nombre, correo, rol)
    WHERE NOT EXISTS (SELECT 1 FROM dbo.EMPLEADO e WHERE e.correo = v.correo);

    INSERT INTO dbo.MESERO (id_empleado, zona_asignada)
    SELECT e.id_empleado, 'Salón' FROM dbo.EMPLEADO e
    WHERE e.correo = 'mesero@restaurante.cr' AND NOT EXISTS (SELECT 1 FROM dbo.MESERO m WHERE m.id_empleado = e.id_empleado);

    INSERT INTO dbo.COCINERO (id_empleado, id_estacion, especialidad)
    SELECT e.id_empleado, (SELECT TOP (1) id_estacion FROM dbo.ESTACION ORDER BY id_estacion), 'General' FROM dbo.EMPLEADO e
    WHERE e.correo = 'cocina@restaurante.cr' AND NOT EXISTS (SELECT 1 FROM dbo.COCINERO c WHERE c.id_empleado = e.id_empleado);

    INSERT INTO dbo.CAJERO (id_empleado, codigo_caja)
    SELECT e.id_empleado, 'CAJA-01' FROM dbo.EMPLEADO e
    WHERE e.correo = 'cajero@restaurante.cr'
      AND NOT EXISTS (SELECT 1 FROM dbo.CAJERO c WHERE c.id_empleado = e.id_empleado)
      AND NOT EXISTS (SELECT 1 FROM dbo.CAJERO c WHERE c.codigo_caja = 'CAJA-01');

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
GO

SELECT 'ROL' AS tabla, COUNT(*) AS filas FROM dbo.ROL
UNION ALL SELECT 'ESTACION', COUNT(*) FROM dbo.ESTACION
UNION ALL SELECT 'CATEGORIA', COUNT(*) FROM dbo.CATEGORIA
UNION ALL SELECT 'METODO_PAGO', COUNT(*) FROM dbo.METODO_PAGO
UNION ALL SELECT 'MESA', COUNT(*) FROM dbo.MESA
UNION ALL SELECT 'INSUMO', COUNT(*) FROM dbo.INSUMO
UNION ALL SELECT 'PRODUCTO', COUNT(*) FROM dbo.PRODUCTO
UNION ALL SELECT 'RECETA', COUNT(*) FROM dbo.RECETA
UNION ALL SELECT 'EMPLEADO', COUNT(*) FROM dbo.EMPLEADO;

SELECT e.correo, r.nombre_rol,
       CASE WHEN m.id_empleado IS NOT NULL THEN 'MESERO' WHEN c.id_empleado IS NOT NULL THEN 'COCINERO' WHEN k.id_empleado IS NOT NULL THEN 'CAJERO' ELSE '-' END AS subclase
FROM dbo.EMPLEADO e
INNER JOIN dbo.ROL r ON r.id_rol = e.id_rol
LEFT JOIN dbo.MESERO m ON m.id_empleado = e.id_empleado
LEFT JOIN dbo.COCINERO c ON c.id_empleado = e.id_empleado
LEFT JOIN dbo.CAJERO k ON k.id_empleado = e.id_empleado
WHERE e.correo LIKE '%@restaurante.cr';
GO
