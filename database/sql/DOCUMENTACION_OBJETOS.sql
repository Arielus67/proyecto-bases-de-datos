/*
================================================================================
 DOCUMENTACION DE OBJETOS - RestauranteDB (SQL Server 2016 o superior)
================================================================================
 Archivos
   RestauranteDB.sql     Base completa. Idempotente: sirve para una base nueva o
                         para actualizar una existente sin borrar datos (agrega
                         columnas faltantes y reemplaza vistas, triggers y
                         procedimientos con CREATE OR ALTER).
   datos_iniciales.sql   Catalogos y un usuario por rol para probar. Solo
                         inserta lo que no existe.

 Orden de ejecucion en SSMS: RestauranteDB.sql y luego datos_iniciales.sql.
 Con sqlcmd usar -I (QUOTED_IDENTIFIER ON):
     sqlcmd -S <servidor> -E -I -i RestauranteDB.sql
     sqlcmd -S <servidor> -E -I -i datos_iniciales.sql

 Usuarios de datos_iniciales.sql (contrasena Restaurante2026!)
     admin@restaurante.cr, mesero@restaurante.cr, cocina@restaurante.cr,
     cajero@restaurante.cr

--------------------------------------------------------------------------------
 TABLAS (21)
--------------------------------------------------------------------------------
 ROL, EMPLEADO (superclase), MESERO / COCINERO / CAJERO (subclases 1:1),
 ESTACION, CATEGORIA, MESA, CLIENTE (cedula cifrada AES-256 en VARBINARY),
 PRODUCTO, INSUMO, RECETA (N:M producto-insumo), PEDIDO, DETALLE_PEDIDO
 (entidad debil), TURNO_CAJA, FACTURA, METODO_PAGO, PAGO, AUDITORIA_PEDIDO,
 ALERTA_STOCK y RESPALDO_BD (historial de respaldos).

 Columnas que faltaban en el script original y que la aplicacion usa:
   FACTURA.anulada_por (FK a EMPLEADO), FACTURA.motivo_anulacion,
   FACTURA.fecha_anulacion.

--------------------------------------------------------------------------------
 RESTRICCIONES DE INTEGRIDAD AGREGADAS
--------------------------------------------------------------------------------
 CK_PEDIDO_Anulacion     Un pedido ANULADO debe tener quien lo anulo y motivo.
 CK_FACTURA_Anulacion    Una factura ANULADA debe tener quien, motivo y fecha.
 CK_FACTURA_Total        total = subtotal - descuento + IVA + servicio y el
                         descuento no supera el subtotal.
 CK_TURNO_CAJA_Cierre    Un turno cerrado tiene monto final y no cierra antes de
                         abrir.
 Si la base ya tiene filas que las incumplen, el script avisa y no las crea.

--------------------------------------------------------------------------------
 INDICES
--------------------------------------------------------------------------------
 IX_DETALLE_PEDIDO_Cocina, IX_PEDIDO_MesaEstado, IX_FACTURA_FechaTotal,
 IX_PRODUCTO_CategoriaActivo           (originales)
 IX_PEDIDO_Estado_Fecha                Consultas de cocina, mesero y caja cada 5 s.
 IX_FACTURA_Turno                      Resumen del cierre de turno.
 IX_ALERTA_STOCK_Pendiente             Alertas pendientes por insumo.
 UQ_TURNO_CAJA_Abierto (filtrado)      Un turno abierto por cajero.
 UQ_PEDIDO_MesaAbierta (filtrado)      Un pedido abierto por mesa: impide
                                       comandas duplicadas por concurrencia.

--------------------------------------------------------------------------------
 VISTAS
--------------------------------------------------------------------------------
 vw_menu_activo, vw_mesas_estado (ahora incluye pedidos ENTREGADO como abiertos,
 igual que la aplicacion), vw_cola_cocina, vw_factura_ticket.

--------------------------------------------------------------------------------
 TRIGGERS (todos soportan operaciones de varias filas)
--------------------------------------------------------------------------------
 trg_producto_baja_logica       INSTEAD OF DELETE en PRODUCTO: baja logica.
 trg_pedido_auditoria           Bitacora de INSERT, UPDATE y DELETE de PEDIDO.
 trg_pedido_estado_final        Un pedido FACTURADO o ANULADO no cambia de estado.
 trg_insumo_alerta_stock        Alerta cuando el stock baja del minimo.
 trg_insumo_alerta_alta         Alerta si un insumo se crea ya bajo el minimo.
 trg_insumo_alertas_reposicion  Al reponer stock, marca atendidas sus alertas.
 trg_factura_no_eliminar        Las facturas no se borran; se anulan.
 trg_factura_inmutable          Montos, pedido, cajero y turno no cambian; una
                                factura anulada no se reactiva.
 trg_pago_inmutable             Los pagos no se modifican ni se borran.

--------------------------------------------------------------------------------
 PROCEDIMIENTOS ALMACENADOS
--------------------------------------------------------------------------------
 sp_registrar_pedido      ControllerMesa@store. Idempotente por token.
                          Valida lineas, productos activos, empleado activo y
                          mesa; bloquea la mesa y los insumos; calcula el
                          inventario necesario agrupado por insumo y avisa que
                          insumo falta antes de descontar.
 sp_agregar_items_pedido  ControllerMesa@agregar. Agrega una ronda a un pedido
                          abierto con numeros de linea consecutivos, descuenta
                          inventario, recalcula el subtotal y lo deja ABIERTO
                          para que cocina vea la ronda nueva.
 sp_cobrar_cuenta         ControllerCaja@cobrar. Valida descuento (0-100),
                          pagos, metodos y cliente; bloquea pedido y turno;
                          IVA 13 % y servicio 10 %; emite factura y pagos.
 sp_anular_pedido         PedidoController@anular. Rechaza pedidos facturados o
                          ya anulados y devuelve el inventario de las lineas
                          PENDIENTE agrupado por insumo.
 sp_reporte_ventas        Reporte por DIA, PRODUCTO, MESERO o PAGO con
                          aislamiento SNAPSHOT (el script activa
                          ALLOW_SNAPSHOT_ISOLATION, sin lo cual fallaba).
 sp_abrir_turno           TurnoCajaController@store. Solo administrador.
 sp_respaldar_bd          RespaldoController@store. .bak con CHECKSUM y
                          RESTORE VERIFYONLY, sin sobrescribir.
 sp_verificar_respaldo    RespaldoController@verificar.

 Correcciones respecto del script original
   - El descuento y la devolucion de inventario usaban UPDATE ... FROM con
     varias filas por insumo: si dos productos compartian un insumo, solo se
     aplicaba una. Ahora se agrupa por insumo antes de actualizar.
   - La validacion de stock negativo nunca se alcanzaba porque el CHECK de
     INSUMO fallaba primero. Ahora se valida antes de descontar.
   - Productos inexistentes o inactivos se descartaban sin aviso.
   - Se podia anular dos veces un pedido y devolver el inventario dos veces.

--------------------------------------------------------------------------------
 CODIGOS DE ERROR PROPIOS (THROW)
--------------------------------------------------------------------------------
 50001 Mesa inexistente               50040 Falta el token de la solicitud
 50002 Stock insuficiente (insumo)    50041 Lineas con formato invalido
 50003 Pedido no cobrable             50042 Pedido sin productos
 50004 Cajero sin turno abierto       50043 Linea o cantidad invalida
 50005 Pagos menores al total         50044 Numero de linea repetido
 50006 Pedido no anulable             50045 Producto inexistente o inactivo
 50010 Borrado de factura             50046 Empleado inexistente o inactivo
 50011 Modificacion de factura        50047 Mesa en mantenimiento
 50012 Modificacion de pago           50048 Pedido cerrado para agregar
 50013 Cambio de estado final         50049 Descuento fuera de 0-100
 50020 Monto inicial invalido         50050 Pagos con formato invalido
 50021 No es cajero activo            50051 Sin pagos
 50022 Turno ya abierto               50052 Monto de pago invalido
 50030 Falta carpeta de respaldo      50053 Metodo de pago inexistente
 50031 Respaldo inexistente           50054 Cliente inexistente
                                      50055 Falta motivo de anulacion
                                      50056 Rango de fechas invalido
                                      50057 Agrupacion de reporte invalida

--------------------------------------------------------------------------------
 PERMISOS POR ROL DE BASE DE DATOS
--------------------------------------------------------------------------------
 rol_administrador  SELECT, INSERT, UPDATE, DELETE y EXECUTE sobre dbo.
 rol_mesero         Lectura de mesas, productos, categorias, pedidos y detalle;
                    UPDATE solo de estados; EXECUTE de sp_registrar_pedido y
                    sp_agregar_items_pedido.
 rol_cocinero       Lectura de pedidos, detalle, productos y mesas; UPDATE solo
                    de estados de cocina.
 rol_cajero         Lectura de pedidos, facturas, pagos, turnos, metodos y
                    cajeros; solo nombre de CLIENTE y EMPLEADO (no cedula ni
                    contrasena_hash); UPDATE solo del cierre de turno; EXECUTE
                    de sp_cobrar_cuenta y sp_anular_pedido. No puede abrir turnos.
 Usuarios sin login app_administrador, app_mesero, app_cocinero y app_cajero.
 Con DB_IMPERSONAR_ROLES=true el middleware AplicarRolSql ejecuta
 EXECUTE AS USER = 'app_<rol>' por solicitud y REVERT al final.

--------------------------------------------------------------------------------
 RESTAURAR UN RESPALDO (nunca se ejecuta automaticamente)
--------------------------------------------------------------------------------
 RESTORE VERIFYONLY FROM DISK = N'C:\Respaldos\RestauranteDB_AAAAMMDD_HHMMSS_mmm.bak' WITH CHECKSUM;
 USE master;
 ALTER DATABASE RestauranteDB SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
 RESTORE DATABASE RestauranteDB FROM DISK = N'C:\Respaldos\RestauranteDB_AAAAMMDD_HHMMSS_mmm.bak'
     WITH REPLACE, CHECKSUM, RECOVERY;
 ALTER DATABASE RestauranteDB SET MULTI_USER;

--------------------------------------------------------------------------------
 CONSULTAS DE VERIFICACION (solo lectura)
--------------------------------------------------------------------------------
*/

USE RestauranteDB;
GO

SELECT name AS objeto, type_desc AS tipo, modify_date
FROM sys.objects
WHERE type IN ('U', 'V', 'P', 'TR')
ORDER BY type_desc, name;

SELECT OBJECT_NAME(object_id) AS tabla, name AS indice, is_unique, has_filter, filter_definition
FROM sys.indexes
WHERE name LIKE 'IX[_]%' OR name LIKE 'UQ[_]%'
ORDER BY tabla, indice;

SELECT OBJECT_NAME(parent_object_id) AS tabla, name AS restriccion, definition
FROM sys.check_constraints
WHERE name LIKE 'CK[_]%'
ORDER BY tabla;

SELECT name AS base, snapshot_isolation_state_desc
FROM sys.databases
WHERE name = 'RestauranteDB';

SELECT r.name AS rol, m.name AS miembro
FROM sys.database_role_members rm
INNER JOIN sys.database_principals r ON r.principal_id = rm.role_principal_id
INNER JOIN sys.database_principals m ON m.principal_id = rm.member_principal_id
WHERE r.name LIKE 'rol[_]%'
ORDER BY r.name;

SELECT id_mesa, COUNT(*) AS pedidos_abiertos
FROM PEDIDO
WHERE estado IN ('ABIERTO', 'EN_PREPARACION', 'LISTO', 'ENTREGADO')
GROUP BY id_mesa
HAVING COUNT(*) > 1;
GO
