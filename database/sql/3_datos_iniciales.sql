USE RestauranteDB
GO

SET NOCOUNT ON
SET XACT_ABORT ON
GO

BEGIN TRY
    BEGIN TRANSACTION;

    INSERT INTO dbo.ROL (nombre_rol)
    SELECT v.nombre FROM (VALUES ('Administrador'), ('Mesero'), ('Cocinero'), ('Cajero')) v(nombre)
    WHERE NOT EXISTS (SELECT 1 FROM dbo.ROL r WHERE r.nombre_rol = v.nombre)

    INSERT INTO dbo.PERMISO (clave, modulo, descripcion)
    SELECT v.clave, v.modulo, v.descripcion FROM (VALUES
        ('dashboard.ver', 'Dashboard', 'Ver el dashboard'),
        ('roles.ver', 'Roles', 'Ver roles y sus permisos'),
        ('roles.crear', 'Roles', 'Crear roles'),
        ('roles.editar', 'Roles', 'Editar roles y asignar permisos'),
        ('roles.eliminar', 'Roles', 'Eliminar roles'),
        ('empleados.ver', 'Empleados', 'Ver empleados'),
        ('empleados.crear', 'Empleados', 'Crear empleados'),
        ('empleados.editar', 'Empleados', 'Editar empleados y cerrar sus sesiones'),
        ('empleados.eliminar', 'Empleados', 'Eliminar empleados'),
        ('categorias.ver', 'Categorías', 'Ver categorías'),
        ('categorias.crear', 'Categorías', 'Crear categorías'),
        ('categorias.editar', 'Categorías', 'Editar categorías'),
        ('categorias.eliminar', 'Categorías', 'Eliminar categorías'),
        ('estaciones.ver', 'Estaciones', 'Ver estaciones'),
        ('estaciones.crear', 'Estaciones', 'Crear estaciones'),
        ('estaciones.editar', 'Estaciones', 'Editar estaciones'),
        ('estaciones.eliminar', 'Estaciones', 'Eliminar estaciones'),
        ('productos.ver', 'Productos', 'Ver productos'),
        ('productos.crear', 'Productos', 'Crear productos'),
        ('productos.editar', 'Productos', 'Editar productos'),
        ('productos.eliminar', 'Productos', 'Eliminar productos'),
        ('insumos.ver', 'Insumos', 'Ver insumos'),
        ('insumos.crear', 'Insumos', 'Crear insumos'),
        ('insumos.editar', 'Insumos', 'Editar insumos'),
        ('insumos.eliminar', 'Insumos', 'Eliminar insumos'),
        ('recetas.ver', 'Recetas', 'Ver recetas'),
        ('recetas.crear', 'Recetas', 'Crear recetas'),
        ('recetas.editar', 'Recetas', 'Editar recetas'),
        ('recetas.eliminar', 'Recetas', 'Eliminar recetas'),
        ('mesas.ver', 'Mesas', 'Ver mesas'),
        ('mesas.crear', 'Mesas', 'Crear mesas'),
        ('mesas.editar', 'Mesas', 'Editar mesas'),
        ('mesas.eliminar', 'Mesas', 'Eliminar mesas'),
        ('clientes.ver', 'Clientes', 'Ver clientes'),
        ('clientes.crear', 'Clientes', 'Crear clientes'),
        ('clientes.editar', 'Clientes', 'Editar clientes'),
        ('clientes.eliminar', 'Clientes', 'Eliminar clientes'),
        ('metodos_pago.ver', 'Métodos de pago', 'Ver métodos de pago'),
        ('metodos_pago.crear', 'Métodos de pago', 'Crear métodos de pago'),
        ('metodos_pago.editar', 'Métodos de pago', 'Editar métodos de pago'),
        ('metodos_pago.eliminar', 'Métodos de pago', 'Eliminar métodos de pago'),
        ('turnos.ver', 'Turnos de caja', 'Ver turnos de caja'),
        ('turnos.crear', 'Turnos de caja', 'Abrir turnos de caja'),
        ('turnos.editar', 'Turnos de caja', 'Editar turnos de caja'),
        ('pedidos.ver', 'Pedidos', 'Ver pedidos'),
        ('pedidos.anular', 'Pedidos', 'Anular pedidos'),
        ('facturas.ver', 'Facturas', 'Ver facturas'),
        ('facturas.anular', 'Facturas', 'Anular facturas'),
        ('pagos.ver', 'Pagos', 'Ver pagos'),
        ('reportes.ver', 'Reportes', 'Ver reportes de ventas'),
        ('auditoria.ver', 'Auditoría', 'Ver la auditoría de pedidos'),
        ('alertas.ver', 'Alertas de stock', 'Ver alertas de stock'),
        ('alertas.atender', 'Alertas de stock', 'Marcar alertas como atendidas'),
        ('respaldos.ver', 'Respaldos', 'Ver el historial de respaldos'),
        ('respaldos.generar', 'Respaldos', 'Generar y verificar respaldos'),
        ('respaldos.restaurar', 'Respaldos', 'Restaurar la base de datos'),
        ('mesero.usar', 'Operación', 'Tomar y entregar pedidos en el salón'),
        ('cocina.usar', 'Operación', 'Preparar pedidos en cocina'),
        ('caja.usar', 'Operación', 'Cobrar, emitir facturas y cerrar turnos de caja'),
        ('caja.supervisar', 'Operación', 'Cobrar y consultar en nombre de cualquier cajero')
    ) v(clave, modulo, descripcion)
    WHERE NOT EXISTS (SELECT 1 FROM dbo.PERMISO p WHERE p.clave = v.clave)

    INSERT INTO dbo.ROL_PERMISO (id_rol, id_permiso)
    SELECT r.id_rol, p.id_permiso
    FROM dbo.ROL r
    INNER JOIN dbo.PERMISO p ON r.nombre_rol = 'Administrador'
        OR (NOT EXISTS (SELECT 1 FROM dbo.ROL_PERMISO x WHERE x.id_rol = r.id_rol)
            AND p.clave = CASE r.nombre_rol WHEN 'Mesero' THEN 'mesero.usar' WHEN 'Cocinero' THEN 'cocina.usar' WHEN 'Cajero' THEN 'caja.usar' END)
    WHERE NOT EXISTS (SELECT 1 FROM dbo.ROL_PERMISO rp WHERE rp.id_rol = r.id_rol AND rp.id_permiso = p.id_permiso)

    INSERT INTO dbo.ESTACION (nombre_estacion)
    SELECT v.nombre FROM (VALUES ('Cocina caliente'), ('Bebidas'), ('Repostería')) v(nombre)
    WHERE NOT EXISTS (SELECT 1 FROM dbo.ESTACION e WHERE e.nombre_estacion = v.nombre)

    INSERT INTO dbo.CATEGORIA (nombre_categoria, descripcion)
    SELECT v.nombre, v.descripcion FROM (VALUES
        ('Platos fuertes', 'Platos principales de la casa'),
        ('Entradas', 'Para compartir'),
        ('Bebidas', 'Naturales y calientes'),
        ('Postres', 'Dulces de la casa')
    ) v(nombre, descripcion)
    WHERE NOT EXISTS (SELECT 1 FROM dbo.CATEGORIA c WHERE c.nombre_categoria = v.nombre)

    INSERT INTO dbo.METODO_PAGO (nombre_metodo)
    SELECT v.nombre FROM (VALUES ('Efectivo'), ('Tarjeta'), ('SINPE Móvil')) v(nombre)
    WHERE NOT EXISTS (SELECT 1 FROM dbo.METODO_PAGO m WHERE m.nombre_metodo = v.nombre)

    INSERT INTO dbo.MESA (numero_mesa, capacidad, zona, estado)
    SELECT v.numero, v.capacidad, v.zona, 'DISPONIBLE' FROM (VALUES
        (1, 2, 'Salón'), (2, 4, 'Salón'), (3, 4, 'Salón'), (4, 6, 'Salón'),
        (5, 2, 'Terraza'), (6, 4, 'Terraza'), (7, 4, 'Terraza'), (8, 8, 'Terraza')
    ) v(numero, capacidad, zona)
    WHERE NOT EXISTS (SELECT 1 FROM dbo.MESA m WHERE m.numero_mesa = v.numero)

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
    WHERE NOT EXISTS (SELECT 1 FROM dbo.INSUMO i WHERE i.nombre_insumo = v.nombre)

    INSERT INTO dbo.PRODUCTO (nombre_producto, descripcion, precio, id_categoria, id_estacion, activo, foto)
    SELECT v.nombre, v.descripcion, v.precio, c.id_categoria, e.id_estacion, 1, v.foto
    FROM (VALUES
        ('Casado con pollo', 'Arroz, frijoles, plátano maduro y pollo', 4500.00, 'Platos fuertes', 'Cocina caliente', 'img/productos/predeterminadas/casado-con-pollo.svg'),
        ('Casado con bistec', 'Arroz, frijoles, plátano maduro y bistec', 5200.00, 'Platos fuertes', 'Cocina caliente', 'img/productos/predeterminadas/casado-con-bistec.svg'),
        ('Arroz con pollo', 'Arroz amarillo con pollo desmenuzado', 4200.00, 'Platos fuertes', 'Cocina caliente', 'img/productos/predeterminadas/arroz-con-pollo.svg'),
        ('Gallo pinto con huevo', 'Gallo pinto, huevo frito y plátano', 3200.00, 'Entradas', 'Cocina caliente', 'img/productos/predeterminadas/gallo-pinto-con-huevo.svg'),
        ('Patacones', 'Patacones con frijoles molidos', 2800.00, 'Entradas', 'Cocina caliente', 'img/productos/predeterminadas/patacones.svg'),
        ('Refresco natural', 'Fruta de temporada', 1500.00, 'Bebidas', 'Bebidas', 'img/productos/predeterminadas/refresco-natural.svg'),
        ('Café chorreado', 'Café de la casa', 1200.00, 'Bebidas', 'Bebidas', 'img/productos/predeterminadas/cafe-chorreado.svg'),
        ('Tres leches', 'Queque tres leches', 2500.00, 'Postres', 'Repostería', 'img/productos/predeterminadas/tres-leches.svg')
    ) v(nombre, descripcion, precio, categoria, estacion, foto)
    INNER JOIN dbo.CATEGORIA c ON c.nombre_categoria = v.categoria
    INNER JOIN dbo.ESTACION e ON e.nombre_estacion = v.estacion
    WHERE NOT EXISTS (SELECT 1 FROM dbo.PRODUCTO p WHERE p.nombre_producto = v.nombre)

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
    WHERE NOT EXISTS (SELECT 1 FROM dbo.RECETA r WHERE r.id_producto = p.id_producto AND r.id_insumo = i.id_insumo)

    DECLARE @hash VARCHAR(255) = '$2y$12$8dZziRsu0nf02qrdnPihOeQBzK0G4JKFE6ZZpcX5oEsH.Z6r7zKjW'

    INSERT INTO dbo.EMPLEADO (nombre, apellido, correo, contrasena_hash, id_rol, activo)
    SELECT v.nombre, 'Prueba', v.correo, @hash,
           (SELECT r.id_rol FROM dbo.ROL r WHERE r.nombre_rol = v.rol), 1
    FROM (VALUES
        ('Admin', 'admin@restaurante.cr', 'Administrador'),
        ('Mesero', 'mesero@restaurante.cr', 'Mesero'),
        ('Cocinero', 'cocina@restaurante.cr', 'Cocinero'),
        ('Cajero', 'cajero@restaurante.cr', 'Cajero')
    ) v(nombre, correo, rol)
    WHERE NOT EXISTS (SELECT 1 FROM dbo.EMPLEADO e WHERE e.correo = v.correo)

    INSERT INTO dbo.MESERO (id_empleado, zona_asignada)
    SELECT e.id_empleado, 'Salón' FROM dbo.EMPLEADO e
    WHERE e.correo = 'mesero@restaurante.cr' AND NOT EXISTS (SELECT 1 FROM dbo.MESERO m WHERE m.id_empleado = e.id_empleado)

    INSERT INTO dbo.COCINERO (id_empleado, id_estacion, especialidad)
    SELECT e.id_empleado, (SELECT TOP (1) id_estacion FROM dbo.ESTACION ORDER BY id_estacion), 'General' FROM dbo.EMPLEADO e
    WHERE e.correo = 'cocina@restaurante.cr' AND NOT EXISTS (SELECT 1 FROM dbo.COCINERO c WHERE c.id_empleado = e.id_empleado)

    INSERT INTO dbo.CAJERO (id_empleado, codigo_caja)
    SELECT e.id_empleado, 'CAJA-01' FROM dbo.EMPLEADO e
    WHERE e.correo = 'cajero@restaurante.cr'
      AND NOT EXISTS (SELECT 1 FROM dbo.CAJERO c WHERE c.id_empleado = e.id_empleado)
      AND NOT EXISTS (SELECT 1 FROM dbo.CAJERO c WHERE c.codigo_caja = 'CAJA-01')

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW
END CATCH
GO

SELECT 'ROL' AS tabla, COUNT(*) AS filas FROM dbo.ROL
UNION ALL SELECT 'ESTACION', COUNT(*) FROM dbo.ESTACION
UNION ALL SELECT 'CATEGORIA', COUNT(*) FROM dbo.CATEGORIA
UNION ALL SELECT 'METODO_PAGO', COUNT(*) FROM dbo.METODO_PAGO
UNION ALL SELECT 'MESA', COUNT(*) FROM dbo.MESA
UNION ALL SELECT 'INSUMO', COUNT(*) FROM dbo.INSUMO
UNION ALL SELECT 'PRODUCTO', COUNT(*) FROM dbo.PRODUCTO
UNION ALL SELECT 'RECETA', COUNT(*) FROM dbo.RECETA
UNION ALL SELECT 'EMPLEADO', COUNT(*) FROM dbo.EMPLEADO
UNION ALL SELECT 'PERMISO', COUNT(*) FROM dbo.PERMISO
UNION ALL SELECT 'ROL_PERMISO', COUNT(*) FROM dbo.ROL_PERMISO

SELECT e.correo, r.nombre_rol,
       CASE WHEN m.id_empleado IS NOT NULL THEN 'MESERO' WHEN c.id_empleado IS NOT NULL THEN 'COCINERO' WHEN k.id_empleado IS NOT NULL THEN 'CAJERO' ELSE '-' END AS subclase
FROM dbo.EMPLEADO e
INNER JOIN dbo.ROL r ON r.id_rol = e.id_rol
LEFT JOIN dbo.MESERO m ON m.id_empleado = e.id_empleado
LEFT JOIN dbo.COCINERO c ON c.id_empleado = e.id_empleado
LEFT JOIN dbo.CAJERO k ON k.id_empleado = e.id_empleado
WHERE e.correo LIKE '%@restaurante.cr'
GO
