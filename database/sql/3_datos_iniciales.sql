USE RestauranteDB;
GO


-- =========================================
-- ROLES
-- =========================================

INSERT INTO ROL (nombre_rol)
VALUES
('Administrador'),
('Mesero'),
('Cocinero'),
('Cajero');
GO


-- =========================================
-- PERMISOS
-- =========================================

INSERT INTO PERMISO (clave, modulo, descripcion)
VALUES
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
('caja.supervisar', 'Operación', 'Cobrar y consultar en nombre de cualquier cajero');
GO


-- =========================================
-- PERMISOS DE CADA ROL
-- =========================================

-- El Administrador recibe todos los permisos
INSERT INTO ROL_PERMISO (id_rol, id_permiso)
SELECT 1, id_permiso FROM PERMISO;

INSERT INTO ROL_PERMISO (id_rol, id_permiso)
SELECT 2, id_permiso FROM PERMISO WHERE clave = 'mesero.usar';

INSERT INTO ROL_PERMISO (id_rol, id_permiso)
SELECT 3, id_permiso FROM PERMISO WHERE clave = 'cocina.usar';

INSERT INTO ROL_PERMISO (id_rol, id_permiso)
SELECT 4, id_permiso FROM PERMISO WHERE clave = 'caja.usar';
GO


-- =========================================
-- ESTACIONES
-- =========================================

INSERT INTO ESTACION (nombre_estacion)
VALUES
('Cocina caliente'),
('Bebidas'),
('Repostería');
GO


-- =========================================
-- CATEGORIAS
-- =========================================

INSERT INTO CATEGORIA (nombre_categoria, descripcion)
VALUES
('Platos fuertes', 'Platos principales de la casa'),
('Entradas', 'Para compartir'),
('Bebidas', 'Naturales y calientes'),
('Postres', 'Dulces de la casa');
GO


-- =========================================
-- METODOS DE PAGO
-- =========================================

INSERT INTO METODO_PAGO (nombre_metodo)
VALUES
('Efectivo'),
('Tarjeta'),
('SINPE Móvil');
GO


-- =========================================
-- MESAS
-- =========================================

INSERT INTO MESA (numero_mesa, capacidad, zona, estado)
VALUES
(1, 2, 'Salón', 'DISPONIBLE'),
(2, 4, 'Salón', 'DISPONIBLE'),
(3, 4, 'Salón', 'DISPONIBLE'),
(4, 6, 'Salón', 'DISPONIBLE'),
(5, 2, 'Terraza', 'DISPONIBLE'),
(6, 4, 'Terraza', 'DISPONIBLE'),
(7, 4, 'Terraza', 'DISPONIBLE'),
(8, 8, 'Terraza', 'DISPONIBLE');
GO


-- =========================================
-- INSUMOS
-- =========================================

INSERT INTO INSUMO (nombre_insumo, unidad_medida, stock_actual, stock_minimo, costo_unitario)
VALUES
('Arroz', 'kg', 25.00, 5.00, 900.00),
('Frijoles', 'kg', 15.00, 3.00, 1400.00),
('Pollo', 'kg', 12.00, 3.00, 3200.00),
('Carne de res', 'kg', 10.00, 2.00, 5500.00),
('Plátano maduro', 'unidad', 40.00, 10.00, 250.00),
('Huevo', 'unidad', 60.00, 12.00, 120.00),
('Café molido', 'kg', 3.00, 0.50, 6000.00),
('Leche', 'L', 12.00, 3.00, 900.00),
('Fruta natural', 'kg', 8.00, 2.00, 1500.00),
('Azúcar', 'kg', 10.00, 2.00, 800.00);
GO


-- =========================================
-- PRODUCTOS
-- =========================================

INSERT INTO PRODUCTO (nombre_producto, descripcion, precio, id_categoria, id_estacion, activo, foto)
VALUES
('Casado con pollo', 'Arroz, frijoles, plátano maduro y pollo', 4500.00, 1, 1, 1, 'img/productos/predeterminadas/casado-con-pollo.svg'),
('Casado con bistec', 'Arroz, frijoles, plátano maduro y bistec', 5200.00, 1, 1, 1, 'img/productos/predeterminadas/casado-con-bistec.svg'),
('Arroz con pollo', 'Arroz amarillo con pollo desmenuzado', 4200.00, 1, 1, 1, 'img/productos/predeterminadas/arroz-con-pollo.svg'),
('Gallo pinto con huevo', 'Gallo pinto, huevo frito y plátano', 3200.00, 2, 1, 1, 'img/productos/predeterminadas/gallo-pinto-con-huevo.svg'),
('Patacones', 'Patacones con frijoles molidos', 2800.00, 2, 1, 1, 'img/productos/predeterminadas/patacones.svg'),
('Refresco natural', 'Fruta de temporada', 1500.00, 3, 2, 1, 'img/productos/predeterminadas/refresco-natural.svg'),
('Café chorreado', 'Café de la casa', 1200.00, 3, 2, 1, 'img/productos/predeterminadas/cafe-chorreado.svg'),
('Tres leches', 'Queque tres leches', 2500.00, 4, 3, 1, 'img/productos/predeterminadas/tres-leches.svg');
GO


-- =========================================
-- RECETAS
-- =========================================

INSERT INTO RECETA (id_producto, id_insumo, cantidad)
VALUES
(1, 1, 0.20), (1, 2, 0.15), (1, 5, 1.00), (1, 3, 0.25),
(2, 1, 0.20), (2, 2, 0.15), (2, 5, 1.00), (2, 4, 0.25),
(3, 1, 0.25), (3, 3, 0.20),
(4, 1, 0.15), (4, 2, 0.10), (4, 6, 1.00), (4, 5, 1.00),
(5, 5, 2.00), (5, 2, 0.10),
(6, 9, 0.20), (6, 10, 0.03),
(7, 7, 0.02), (7, 10, 0.01),
(8, 8, 0.15), (8, 6, 1.00), (8, 10, 0.05);
GO


-- =========================================
-- EMPLEADOS DE PRUEBA
-- =========================================

INSERT INTO EMPLEADO (nombre, apellido, correo, contrasena_hash, id_rol, activo)
VALUES
('Admin', 'Prueba', 'admin@restaurante.cr', '$2y$12$8dZziRsu0nf02qrdnPihOeQBzK0G4JKFE6ZZpcX5oEsH.Z6r7zKjW', 1, 1),
('Mesero', 'Prueba', 'mesero@restaurante.cr', '$2y$12$8dZziRsu0nf02qrdnPihOeQBzK0G4JKFE6ZZpcX5oEsH.Z6r7zKjW', 2, 1),
('Cocinero', 'Prueba', 'cocina@restaurante.cr', '$2y$12$8dZziRsu0nf02qrdnPihOeQBzK0G4JKFE6ZZpcX5oEsH.Z6r7zKjW', 3, 1),
('Cajero', 'Prueba', 'cajero@restaurante.cr', '$2y$12$8dZziRsu0nf02qrdnPihOeQBzK0G4JKFE6ZZpcX5oEsH.Z6r7zKjW', 4, 1);
GO

INSERT INTO MESERO (id_empleado, zona_asignada)
VALUES (2, 'Salón');

INSERT INTO COCINERO (id_empleado, id_estacion, especialidad)
VALUES (3, 1, 'General');

INSERT INTO CAJERO (id_empleado, codigo_caja)
VALUES (4, 'CAJA-01');
GO
