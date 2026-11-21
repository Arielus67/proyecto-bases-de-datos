# Informe de auditoría y mejora — La Cuchara de Luison

Fecha: 20 de noviembre de 2026 (actualizado el 21 con la primera ejecución del kit en SQL Server). Alcance: proyecto Laravel 12 completo y los tres scripts de SQL Server (`database/sql/`).

> **Cómo leer este informe.** Cada prueba indica dónde se ejecutó:
>
> - **Aquí**: en el entorno de la auditoría, con Linux, PHP 8.3, 2 CPU y SQLite en lugar de SQL Server. MongoDB no estaba disponible.
> - **Kit**: ejecutada con `php artisan restaurante:pruebas-sqlserver` en el equipo del desarrollador, sobre SQL Server real y sobre una copia restaurada de la base (`RestauranteDB_Pruebas`), nunca sobre la base principal. El apartado 14 resume esa ejecución.
>
> Ninguna prueba se marca como aprobada sin haberse ejecutado.

## 1. Resumen

| Área | Resultado |
|---|---|
| Scripts SQL | Sin comentarios, sin `;` innecesarios y 107 restricciones con nombre. Tablas de permisos y función de permisos nuevas. Corrección del orden de bloqueo que podía causar *deadlocks*. Bajan de 3 139 a 2 855 líneas aunque tienen 3 tablas y 5 procedimientos más |
| Scripts obsoletos | Se eliminaron 5 scripts duplicados o superados (3 986 líneas). En total, el SQL del repositorio pasa de 7 125 a 2 855 líneas |
| Roles y permisos | 60 permisos por módulo y operación, rol configurable desde el panel, control en cada ruta, menú y botones según permiso, y validación también en SQL Server |
| Limpieza Laravel | Se eliminaron 31 archivos sin uso, se corrigió el reporte de ventas (no tenía ruta) y se quitó código que adivinaba nombres de columna |
| Nombre comercial | «La Cuchara de Luison» en todas las pantallas, el ticket, el login, la bienvenida, los respaldos y el favicon propio |
| Pruebas automáticas (aquí) | 173 aprobadas, 2 omitidas (dependen de procedimientos de SQL Server), 0 fallidas |
| Seguridad y concurrencia por HTTP (aquí) | 35 de 35 aprobadas |
| Fallos y caídas (aquí) | 6 de 6 aprobadas |
| Estrés de 5 a 100 usuarios (aquí) | 0 solicitudes fallidas en todos los niveles; tras la optimización, 2,7 veces más solicitudes por segundo |
| Pruebas sobre SQL Server (kit, 1.ª ejecución) | 23 de 28 aprobadas. Concurrencia 9 de 9 y respaldos 7 de 7. Las fallas venían de un nombre de restricción repetido en el script 1 y de 3 errores del propio kit, ya corregidos; esas fases deben repetirse (apartado 14) |

## 2. Archivos

### 2.1 Modificados

| Archivo | Cambio |
|---|---|
| `database/sql/1_RestauranteDB_estructura.sql` | Restricciones con nombre (`PK_`, `FK_`, `UQ_`, `CK_`, `DF_`); tablas `PERMISO` y `ROL_PERMISO`; renombrado automático de las restricciones que SQL Server había nombrado en bases existentes; índices `IX_DETALLE_PEDIDO_Producto` e `IX_PAGO_Factura`; `READ_COMMITTED_SNAPSHOT ON` |
| `database/sql/2_RestauranteDB_programacion.sql` | Sin comentarios ni `;` innecesarios; los 33 procedimientos CRUD de una sola instrucción ya no abren transacciones innecesarias; los 11 `*_consultar` ya no tienen un TRY/CATCH que solo relanzaba el error; nuevos `fn_tiene_permiso`, `vw_permisos_empleado` y `sp_rol_reemplazar_permisos`; `sp_rol_insertar` y `sp_rol_actualizar` reciben los permisos; los procedimientos de negocio validan permisos y bloquean en orden fijo |
| `database/sql/3_datos_iniciales.sql` | Catálogo de 60 permisos y asignación inicial a los 4 roles; búsqueda de rol por nombre exacto en lugar de `LIKE '%...%'` |
| `routes/web.php` | Cada ruta exige su permiso (`can:`); catálogos registrados en un solo bucle; nueva ruta del reporte de ventas |
| `app/Models/Rol.php`, `Empleado.php` | Relación con permisos, `tienePermiso()`, pantalla de inicio según permisos, puesto operativo según los permisos del rol |
| `app/Http/Controllers/RolController.php` | Alta y edición de roles con permisos y protección contra quedarse sin administrador de roles |
| `app/Http/Controllers/EmpleadoController.php` | El puesto (mesero, cocinero o cajero) se decide por los permisos del rol |
| `app/Http/Controllers/ControllerCaja.php` | «Es administrador» pasa a ser el permiso `caja.supervisar` |
| `app/Http/Controllers/ControllerCocina.php` | Si MongoDB no responde, muestra la cola desde SQL Server en modo de solo lectura en lugar de un error 500 |
| `app/Http/Controllers/DashboardController.php` | Se quitó la función que probaba varios nombres de columna posibles |
| `app/Http/Controllers/PedidoController.php`, `AuditoriaController.php` | Validación de los filtros: un valor manipulado ya no puede producir un error 500 en SQL Server |
| `app/Http/Middleware/AplicarRolSql.php` | El usuario de SQL Server se elige según el módulo (`rol.sql:mesero`) y no según el nombre del rol |
| `app/Http/Middleware/VerificarSesionUnica.php` | También cierra la sesión de usuarios desactivados (antes lo hacía `VerificarRol`) |
| `app/Providers/AppServiceProvider.php` | `Gate::before` con los permisos de la base y directiva Blade `@accesible` |
| `config/database.php` | Tiempo de espera de MongoDB configurable (`DB_MONGO_TIMEOUT_MS`) |
| `config/app.php`, `.env`, `.env.example` | Nombre comercial; `QUEUE_CONNECTION=file` (driver inexistente) corregido a `sync`; variable de Vite eliminada |
| Vistas de roles, empleados, menú lateral, componentes `actions` y `new-button` | Matriz de permisos, menú y botones según los permisos de cada ruta |
| Layouts, login, bienvenida, ticket, `head` | Nombre comercial y favicon propio |
| `README.md` | Reescrito con los 19 apartados pedidos |

### 2.2 Eliminados

| Archivo | Por qué |
|---|---|
| `database/sql/RestauranteDB.sql`, `datos_iniciales.sql`, `correcciones_auditoria.sql`, `mejoras_restaurante.sql`, `DOCUMENTACION_OBJETOS.sql` | Versiones anteriores de la base. El README ya indicaba que los oficiales son `1_`, `2_` y `3_`; mantener cinco copias distintas era una fuente de errores |
| `database/migrations/*`, `database/factories/UserFactory.php`, `database/seeders/DatabaseSeeder.php`, `app/Models/User.php` | Restos de la instalación inicial de Laravel. La base se crea con los scripts SQL; las migraciones de `mesas` y `productos` creaban tablas que chocarían con las reales |
| `public/css/caja.css`, `cocina.css`, `comandas.css`, `dashboard.css`, `login.css`, `public/css/sistema/entrada.css`, `layout.css`, `public/js/avisos.js`, `public/js/sistema/sondeo.js` | Ninguna vista los cargaba; `sondeo.js` era una copia idéntica de `public/js/sondeo.js` |
| `resources/views/welcome.blade.php`, `layouts/base.blade.php`, `layouts/css.blade.php` | Vistas sin uso |
| `resources/js`, `resources/css`, `vite.config.js`, `package.json` | Solo los usaba `welcome.blade.php`; la plantilla ya trae su CSS compilado |
| `app/Support/CedulaCipher.php` | Nadie lo usaba. El documento del proyecto explica que la cédula se protege con permisos y no se cifra |
| `app/Http/Middleware/VerificarRol.php` | Reemplazado por el middleware `can:` de Laravel con permisos |
| `tests/Unit/RolTipoTest.php`, `tests/Unit/ExampleTest.php` | Probaban el sistema de roles anterior o nada (`assertTrue(true)`) |
| `cambios_completos.patch` | Parche suelto que había quedado dentro del proyecto |

### 2.3 Nuevos

- `app/Models/Permiso.php` y `app/Support/Acceso.php`: comprueba si el usuario puede usar una ruta, para mostrar u ocultar botones.
- `app/Console/Commands/PruebasSqlServer.php`: el kit de pruebas.
- `public/js/modulos/roles.js` y `public/css/modulos/roles.css`.
- `public/img/marca/favicon.*`.
- `docs/ICONOS_Y_LICENCIAS.md` y este informe.
- `docs/pruebas/`: herramientas y resultados en JSON.
- Pruebas `PermisosTest`, `ScriptsSqlTest` y `LoteSqlServerTest`.

## 3. Funcionalidades

**Conservadas**, comprobadas con las 173 pruebas automáticas y con el recorrido en navegador:

- Inicio de sesión con sesión única y límite de intentos.
- Panel con todos los catálogos.
- Pedidos, rondas adicionales, cocina, caja con varios pagos, ticket, cierre de turno, facturas y su anulación.
- Auditoría, alertas de stock, respaldos y restauración.
- Fotos de productos e idempotencia de pedidos.

**Corregidas:**

| Problema encontrado | Corrección |
|---|---|
| El **reporte de ventas** existía (controlador y vista) pero no tenía ruta: era inaccesible | Ruta `admin.reportes.ventas` con permiso `reportes.ver` y enlace en el menú |
| Cobrar una mesa y registrar un pedido nuevo en la misma mesa al mismo tiempo podía producir un **deadlock**: el cobro bloqueaba PEDIDO y luego MESA, y el registro lo hacía al revés | `sp_cobrar_cuenta` y `sp_anular_pedido` bloquean primero la MESA, igual que `sp_registrar_pedido` |
| Con MongoDB caído, la pantalla de **cocina** respondía error 500 | Muestra la cola desde SQL Server con un aviso y sin botones de cambio de estado; los cambios responden 503 con un mensaje claro |
| Filtros como `?fecha=abc` o `?id_pedido=1;DROP...` llegaban a la consulta; en SQL Server la conversión de fecha produce error 500 | Validación de los filtros (estado, fecha y número de pedido) |
| `QUEUE_CONNECTION=file` no es un driver válido de Laravel | `sync` |
| El dashboard «adivinaba» nombres de columna | Usa `nombre_insumo` y `nombre_producto` directamente |
| El favicon era el logotipo de la plantilla MaterialM | Favicon propio de la marca |

## 4. Cambios en SQL Server

- **Limpieza:**
  - Se quitaron todos los comentarios.
  - Se quitaron los `;` que no son obligatorios. Se conservan los que exige SQL Server: antes de `THROW`, en `BEGIN TRANSACTION`, `COMMIT` y `ROLLBACK`, y antes de un `WITH`. La prueba `ScriptsSqlTest` comprueba que se mantengan.
  - Nota: Microsoft recomienda terminar todas las instrucciones con `;`. Se quitaron porque así se pidió, pero agregarlos de nuevo no afecta nada.
- **Nombres claros:**
  - Las 107 restricciones tienen nombre con un patrón fijo (`PK_TABLA`, `FK_TABLA_columna`, `UQ_TABLA_columna`, `CK_TABLA_columna`, `DF_TABLA_columna`). Antes, SQL Server generaba nombres como `PK__PEDIDO__A3E1F7B2`, que no dicen nada en un mensaje de error.
  - En una base ya creada, el script 1 las renombra automáticamente con `sp_rename`.
  - Se quitaron la variable `@tran_propia` (innecesaria en instrucciones únicas) y la variable `@bloqueados`, que solo servía para bloquear.
  - Las tablas y columnas no cambiaron, porque ya eran descriptivas.
- **Simplificación:**
  - Los procedimientos CRUD hacen una sola instrucción, que ya es atómica: se quitó la transacción explícita y se conservó la traducción de errores (50101, 50102, 50103).
  - El descuento de inventario pasa de «bloquear, comprobar y actualizar» (3 instrucciones) a un solo `UPDATE ... WHERE stock_actual >= cantidad` con `OUTPUT`. Es igual de seguro ante la concurrencia y no puede dejar stock negativo.
  - El pedido nuevo se inserta ya con su subtotal. Antes se insertaba con subtotal 0 y luego se actualizaba, lo que dejaba además una fila de auditoría de más.
- **Permisos:**
  - Tablas `PERMISO` y `ROL_PERMISO`, función `fn_tiene_permiso` y vista `vw_permisos_empleado`.
  - `sp_rol_reemplazar_permisos` valida cada permiso y que siga existiendo un empleado activo capaz de editar roles.
  - `sp_empleado_actualizar` y `sp_empleado_eliminar` también impiden dejar el sistema sin administrador de roles.
- **Aislamiento y bloqueos:**
  - `READ_COMMITTED_SNAPSHOT ON`: las lecturas no esperan a los escritores.
  - `UPDLOCK, HOLDLOCK` se mantiene donde hay que leer y luego modificar la misma fila (mesa, pedido, turno, sesión).
  - El reporte de ventas sigue usando `SNAPSHOT`.
- **Respaldos:** la descripción de cada respaldo lleva el nombre comercial.
- **Validación de sintaxis:**
  - Se validaron los tres scripts con el analizador T-SQL de `sqlfluff`: 0 errores.
  - Tres lotes contienen `BACKUP` y `RESTORE`, que esa herramienta no reconoce; al sustituir esas instrucciones también analizan sin errores.
  - La validación definitiva es ejecutarlos en SQL Server: es lo primero que hace el kit.

## 5. Roles y permisos

- **60 permisos** en 20 módulos (lista completa en el README).
- **Crear y editar roles:** una matriz con una fila por módulo, casilla «marcar todo el módulo» y «marcar todos».
- **Eliminar roles:** solo si el rol no tiene empleados.
- **Consultar permisos:** el listado muestra cuántos permisos tiene cada rol y de qué módulos.
- **Control de acceso:**
  - Cada una de las 96 rutas protegidas declara su permiso.
  - Una prueba automática falla si se agrega una ruta sin permiso, o una que pide un permiso inexistente.
  - El menú y los botones se calculan a partir de esas mismas rutas, así que no hay condiciones repetidas en las vistas.
- **Roles actuales:**
  - Administrador recibe todos los permisos.
  - Mesero, Cocinero y Cajero reciben su permiso de operación.
  - Ejecutar el script 3 sobre una base existente asigna estos permisos sin tocar los roles personalizados.
- **En SQL Server:** los procedimientos de registrar pedido, cobrar y anular rechazan a quien no tiene el permiso, aunque la llamada se haga desde SSMS.

## 6. Pruebas de concurrencia

### 6.1 Ejecutadas aquí (HTTP contra la aplicación, base SQLite)

| Prueba | Esperado | Obtenido | Resultado |
|---|---|---|---|
| 20 inicios de sesión simultáneos con la misma cuenta desde 20 equipos | 1 sesión activa | 1 sesión con acceso y 1 fila en `SESION_ACTIVA` | Aprobada |
| 15 administradores crean el mismo rol a la vez | 1 rol, ningún error 500 | 1 rol; 15 respuestas 302 (1 creado y 14 con mensaje de nombre repetido) | Aprobada |
| Ningún rol queda creado sin sus permisos | 0 | 0 | Aprobada |

### 6.2 Kit sobre SQL Server real (ejecutadas: 9 de 9 aprobadas)

| Prueba | Qué comprueba |
|---|---|
| 20 meseros crean pedido en la misma mesa | 1 pedido y 19 rechazos 50007 (no hay pedidos duplicados ni dos pedidos incompatibles en la misma mesa) |
| El mismo pedido enviado 20 veces | 1 pedido y todos reciben el mismo id (idempotencia) |
| 20 cajas cobran el mismo pedido | 1 factura y 1 pago (no hay pagos duplicados) |
| 30 pedidos compiten por 10 unidades | 10 éxitos, 20 rechazos 50002, stock final 0 (no se descuenta dos veces ni queda negativo) |
| 20 rondas al mismo pedido | 21 líneas con número único y subtotal igual a la suma (no se pierden actualizaciones) |
| 15 rondas de cobro y pedido nuevo en la misma mesa | 0 deadlocks (comprueba la corrección del orden de bloqueo) |
| 10 rondas de cobrar y anular el mismo pedido | Gana solo uno y el estado coincide con la factura |
| 5 pedidos que bajan un insumo del mínimo | 1 sola alerta pendiente |
| Error a mitad de transacción | No quedan pedidos, líneas ni stock modificados |

### 6.3 Niveles de aislamiento (kit: la 1.ª ejecución falló por un error del kit; pendiente de repetir)

| Nivel | Qué demuestra la prueba | Uso en el sistema |
|---|---|---|
| READ UNCOMMITTED | Lee datos sin confirmar (lectura sucia) | No se usa: podría cobrar con montos que luego se revierten |
| READ COMMITTED con instantáneas | Lee la última versión confirmada sin esperar | Nivel por defecto: pantallas y listados |
| REPEATABLE READ | Bloquea a quien quiera modificar lo leído | No hace falta: donde importa se usa `UPDLOCK, HOLDLOCK` solo en las filas necesarias |
| SERIALIZABLE | Impide filas fantasma en el rango leído | Se aplica localmente con `HOLDLOCK` (que equivale a SERIALIZABLE en esa tabla) para que no aparezca un segundo pedido abierto o un segundo turno |
| `UPDLOCK, HOLDLOCK` | El segundo lector espera al primero | Mesa, pedido, turno de caja y sesión: leer y modificar sin perder actualizaciones |

## 7. Pruebas de estrés

Ejecutadas **aquí**. Cada nivel duró 20 segundos y cada usuario simulado entró desde una dirección distinta.

- **Mezcla de usuarios:** 40 % meseros, 20 % cocina, 20 % caja y 20 % administradores.
- **Patrón de uso:** cada usuario repite sin pausa su pantalla y sus consultas de actualización automática.
- **Entorno:** 2 CPU y base SQLite. El generador de carga corre en el mismo equipo y le quita CPU al servidor.
- **Alcance:** los tiempos sirven para comparar niveles entre sí. No son la capacidad que tendrá el sistema con SQL Server.

### 7.1 Servidor de desarrollo (`php artisan serve`, un solo proceso PHP)

| Usuarios | Solicitudes | Exitosas | Fallidas | Promedio ms | p95 ms | Máx. ms | Solic./s | CPU % | Memoria MB | Inicio de sesión ms |
|---|---|---|---|---|---|---|---|---|---|---|
| 5 | 1 414 | 1 414 | 0 | 71 | 85 | 590 | 70,7 | 45 | 129 | 1 079 |
| 10 | 1 387 | 1 387 | 0 | 145 | 169 | 640 | 69,3 | 45 | 130 | 1 469 |
| 25 | 1 296 | 1 296 | 0 | 391 | 838 | 901 | 64,8 | 45 | 130 | 3 473 |
| 50 | 1 301 | 1 301 | 0 | 800 | 1 270 | 1 326 | 65,0 | 43 | 130 | 6 895 |
| 100 | 1 363 | 1 363 | 0 | 1 519 | 1 978 | 2 023 | 68,2 | 43 | 131 | 13 727 |

**Punto lento identificado:** con un solo proceso PHP, el servidor usa como máximo un núcleo (CPU al 45 % de 2 núcleos) y atiende unas 70 solicitudes por segundo. A partir de ahí, cada usuario más solo aumenta la cola de espera.

### 7.2 Servidor optimizado (4 procesos PHP, OPcache y cachés de configuración, rutas y vistas)

| Usuarios | Solicitudes | Exitosas | Fallidas | Promedio ms | p95 ms | Máx. ms | Solic./s | CPU % | Memoria MB | Inicio de sesión ms |
|---|---|---|---|---|---|---|---|---|---|---|
| 5 | 3 590 | 3 590 | 0 | 28 | 52 | 553 | 179,5 | 84 | 262 | 708 |
| 10 | 3 734 | 3 734 | 0 | 54 | 88 | 726 | 186,7 | 88 | 266 | 937 |
| 25 | 3 854 | 3 854 | 0 | 130 | 166 | 642 | 192,7 | 89 | 266 | 1 953 |
| 50 | 3 621 | 3 621 | 0 | 278 | 328 | 816 | 181,1 | 89 | 266 | 3 579 |
| 100 | 3 849 | 3 849 | 0 | 528 | 632 | 1 072 | 192,4 | 88 | 266 | 6 828 |

Resultado de la optimización: **2,7 veces** más solicitudes por segundo y el tiempo promedio con 100 usuarios baja de 1 519 ms a 528 ms, ahora limitado por la CPU del equipo.

- No hubo errores HTTP ni deadlocks.
- El registro de Laravel quedó sin errores.
- La pantalla más lenta en todos los niveles fue el dashboard del administrador.
- Los pasos de configuración están en el README, apartado 16.

**En SQL Server (kit):** la 1.ª ejecución se detuvo por un error del kit (`Undefined array key "ok"`), ya corregido; no hay cifras de SQL Server todavía. Al repetirla, el kit repite la prueba con 5, 10, 25, 50 y 100 usuarios ejecutando los procedimientos reales. Mide:

- Tiempo promedio, p95 y máximo, por operación.
- Operaciones fallidas y sus códigos de error.
- Deadlocks, esperas por bloqueo y tiempo esperando bloqueos.
- CPU de SQL Server y memoria de PHP.
- Tiempo promedio de cada procedimiento, tomado de `sys.dm_exec_procedure_stats`.

## 8. Respaldos y restauración

| Prueba | Dónde | Resultado |
|---|---|---|
| Lectura de todos los mensajes de `BACKUP` y `RESTORE` sin cortar la operación (simulación del driver) | Aquí | Aprobada (3 pruebas) |
| Pantalla de respaldos y restauración: permisos, contraseña, confirmación, respaldos no verificados | Aquí | Aprobada (11 pruebas) |
| Crear respaldo en la carpeta configurada y comprobar el archivo | Kit (SQL Server) | Aprobada |
| Registro en `RESPALDO_BD` como verificado | Kit (SQL Server) | Aprobada |
| Restaurar en una base independiente y comparar filas y `CHECKSUM` de todas las tablas | Kit (SQL Server) | Aprobada |
| `sp_restaurar_restaurante` devuelve la base de pruebas al estado del respaldo | Kit (SQL Server) | Aprobada |
| Carpeta inexistente (debe crearse) | Kit (SQL Server) | Aprobada |
| Unidad inexistente y carpeta sin permiso (error claro, nunca «verificado») | Kit (SQL Server) | Aprobada |
| Disco sin espacio | — | No se puede realizar aquí: requiere una unidad pequeña dedicada (por ejemplo un VHD de 50 MB) indicada con `--carpeta` |

## 9. Fallos y caídas

| Prueba | Dónde | Obtenido | Resultado |
|---|---|---|---|
| Base de datos no disponible | Aquí | 500 controlado en 10 ms, sin mostrar rutas ni SQL | Aprobada |
| La base vuelve | Aquí | 200 en la siguiente petición, sin reiniciar nada | Aprobada |
| Base bloqueada 6 s por otro proceso | Aquí | La petición esperó 5,6 s y terminó bien, sin datos a medias | Aprobada |
| Laravel terminado con `kill -9` mientras se creaban roles | Aquí | 71 creados antes de caer; `integrity_check = ok`; 0 roles sin permisos | Aprobada |
| Reinicio del servidor | Aquí | Opera normalmente al volver | Aprobada |
| MongoDB caído con la cocina abierta | Aquí | 200 en 63 ms con aviso y cola desde SQL Server (antes: error 500) | Aprobada |
| Laravel cae durante el registro de un pedido | Kit | 1.ª ejecución no válida: el proceso de prueba falló antes de abrir la transacción («sesión 0») | Repetir |
| Laravel cae durante el cobro (factura y pago) | Kit | 1.ª ejecución no válida, mismo motivo | Repetir |
| Consulta que supera el tiempo permitido (`LOCK_TIMEOUT`) | Kit | Error del kit (MARS), corregido | Repetir |
| Conexión cortada por SQL Server (`KILL`) a mitad de transacción | Kit | No llegó a ejecutarse | Repetir |
| Datos inválidos: JSON roto, cantidad negativa, descuento de 150 %, pago insuficiente, empleado sin permiso | Kit | No llegó a ejecutarse | Repetir |
| Datos inválidos en formularios | Aquí | Cubiertos por las pruebas de validación de PHPUnit | Aprobada |
| SQL Server se detiene o reinicia | — | Prueba manual descrita en el kit (detener y volver a iniciar el servicio) | Pendiente (manual) |
| Falta de espacio en disco | — | No se simuló, por riesgo para el equipo | No realizable aquí |

**Operaciones que necesitan recuperación:**

- **Pedido y cobro:** se protegen con transacciones (`XACT_ABORT ON`). Si la conexión se corta, SQL Server revierte todo. El pedido además es idempotente: reintentarlo con el mismo token no lo duplica.
- **Comandas de MongoDB:** se reconstruyen desde SQL Server cuando faltan.
- **Respaldos fallidos:** quedan registrados como no verificados.
- **Sesiones:** la sesión de una cuenta abandonada se libera sola.

## 10. Pruebas de seguridad

| Grupo | Pruebas | Aprobadas |
|---|---|---|
| Acceso a rutas sin iniciar sesión | 10 | 10 |
| Módulos y operaciones sin permiso (GET, POST, PUT, DELETE directos) | 10 | 10 |
| Fijación de sesión, cookie HttpOnly y SameSite | 2 | 2 |
| CSRF (sin token y con token inventado) | 2 | 2 |
| XSS (rol con `<script>` en el nombre) | 1 | 1 |
| Inyección SQL (filtros, URL y login) y ninguna tabla eliminada | 6 | 6 |
| Información confidencial (hashes de contraseña fuera de las vistas) | 1 | 1 |
| Manipulación de parámetros (un cajero intenta cobrar a nombre de otro) | 1 (PHPUnit) | 1 |
| Procedimientos y permisos de SQL Server (empleado sin permiso para cobrar) | 1 | Kit, pendiente |

## 11. Iconos y licencias

El inventario completo está en `docs/ICONOS_Y_LICENCIAS.md`.

- **Tabler Icons:** 70 iconos, licencia MIT, uso comercial permitido; si se redistribuyen los archivos hay que conservar el aviso de copyright.
- **Demás recursos:** la plantilla MaterialM, Chart.js, Tailwind y Preline son MIT (Preline además tiene una cláusula de uso justo). Las fuentes DM Sans y JetBrains Mono son SIL OFL.
- **Sin otras bibliotecas de iconos:** no se usan Font Awesome, Bootstrap Icons ni Lucide.
- **Pendiente de verificar:** las fotos de productos subidas desde el panel (`public/img/productos/*.jpg`) tienen origen desconocido.

## 12. Problemas que no pudieron resolverse aquí

1. **SQL Server no está disponible en este entorno**: Microsoft y Docker Hub están bloqueados. Las pruebas de la base real se ejecutan con el kit en el equipo del desarrollador (apartado 14).
2. **MongoDB tampoco**: la cocina solo se probó en modo de respaldo.
3. **`barryvdh/laravel-dompdf` no se usa**, pero Composer no puede actualizar `composer.lock` sin acceso a packagist. En su equipo ejecute `composer remove barryvdh/laravel-dompdf`.
4. **Los documentos `docs/Proyecto_Final_BD2_Restaurante.pdf` y `.docx`** siguen con el nombre y los conteos anteriores (21 tablas, roles fijos). Hay que actualizarlos a mano.
5. **El límite de 10 inicios de sesión por minuto por dirección IP** se conservó sin cambios. Si muchos empleados entran desde la misma IP pública (detrás de un router con NAT hacia un servidor externo), el undécimo tendría que esperar un minuto. Ya existe un límite de 5 intentos por cuenta. Decida usted si conviene subirlo.

## 13. Recomendaciones pendientes

1. Repetir `php artisan restaurante:pruebas-sqlserver --solo=preparar,aislamiento,estres,fallos` y adjuntar a este informe el `.md` que genera en `storage/app/pruebas/`.
2. En producción, servir con IIS, Apache o Nginx con PHP-FPM y OPcache, y ejecutar `config:cache`, `route:cache` y `view:cache`.
3. Copiar los respaldos `.bak` a otro equipo o a la nube; la carpeta local no protege contra la pérdida del disco.
4. Descargar en `public/` los iconos, Chart.js y las fuentes si el restaurante puede quedarse sin Internet.
5. Activar `DB_IMPERSONAR_ROLES=true` cuando la cuenta de conexión tenga el permiso IMPERSONATE.
6. Revisar el licenciamiento de las fotos subidas por los usuarios.

## 14. Primera ejecución del kit en SQL Server

Ejecutada el 21 de noviembre de 2026 en el equipo del desarrollador, sobre `RestauranteDB_Pruebas` (copia restaurada desde un respaldo `COPY_ONLY` de la base principal). Resultado: **23 de 28 aprobadas**.

| Fase | Resultado | Causa de la falla | Corrección |
|---|---|---|---|
| Preparar | Respaldo `COPY_ONLY`, restauración, scripts 2 y 3 sin errores y 60 permisos cargados. **El script 1 falló** | La tabla `FACTURA` tenía dos restricciones que solo se distinguían por mayúsculas (`CK_FACTURA_total` y `CK_FACTURA_Total`); SQL Server no distingue mayúsculas en los nombres | La restricción de tabla ahora se llama `CK_FACTURA_CalculoTotal`, el script la renombra en bases existentes y una prueba automática impide nombres repetidos |
| Respaldos | 7 de 7 aprobadas | — | — |
| Concurrencia | 9 de 9 aprobadas, sin deadlocks | — | — |
| Aislamiento | Falló | Error del kit: el controlador abría transacciones con MARS activo, que SQL Server no permite dejar abiertas entre lotes | Las conexiones del kit desactivan MARS y cierran cada cursor |
| Estrés | Falló | Error del kit: leía una clave `ok` que los trabajadores de estrés no devuelven | Corregido; si un trabajador cae, se cuenta como error y no detiene la fase |
| Fallos | 2 marcadas como aprobadas, pero **no válidas** (el proceso de prueba no abrió la transacción); luego se detuvo por el mismo error de MARS | Error del kit | El proceso de prueba usa la conexión sin MARS, solo se termina si sigue vivo y la prueba exige una sesión real (`spid > 0`) para aprobar |

Las fallas no venían de la aplicación ni de los procedimientos, salvo el nombre repetido del script 1. Las fases afectadas se repiten con:

```
php artisan restaurante:pruebas-sqlserver --solo=preparar,aislamiento,estres,fallos
```

