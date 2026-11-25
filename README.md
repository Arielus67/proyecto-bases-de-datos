# La Cuchara de Luison — Sistema de gestión del restaurante

## 1. Nombre del proyecto

**La Cuchara de Luison**, sistema de pedidos, cocina, caja e inventario. Proyecto final de IF0010 Bases de Datos II (Sede del Caribe, II ciclo 2026).

## 2. Descripción general

Aplicación web hecha en Laravel 12. Trabaja con dos bases de datos:

- **SQL Server** (`RestauranteDB`): mesas, menú, inventario, recetas, pedidos, facturas, pagos, turnos de caja, roles y permisos, auditoría, alertas de inventario, respaldos y sesiones activas. Las reglas de negocio importantes viven en procedimientos almacenados y triggers.
- **MongoDB** (`restaurante_mongo`): comandas de cocina con notas libres, bitácora de eventos y tiempos de preparación.

## 3. Objetivo del sistema

Que el salón, la cocina y la caja trabajen sobre la misma información sin errores. Los objetivos concretos son estos:

- Que no se dupliquen pedidos ni cobros.
- Que el inventario se descuente una sola vez.
- Que cada persona vea solo lo que su rol le permite.

## 4. Tecnologías utilizadas

| Capa | Tecnología |
|---|---|
| Servidor web | PHP 8.2 o superior, Laravel 12 |
| Base relacional | SQL Server 2019 o superior (Developer o Express) |
| Base no relacional | MongoDB 6 o superior |
| Interfaz | Plantilla MaterialM (Tailwind CSS), Tabler Icons, Chart.js |
| Pruebas | PHPUnit 11 (`php artisan test`) y un kit de pruebas para SQL Server (`php artisan restaurante:pruebas-sqlserver`) |

## 5. Requisitos para ejecutarlo

- PHP 8.2 o superior con las extensiones `pdo_sqlsrv`, `sqlsrv`, `mongodb`, `openssl`, `mbstring` y `fileinfo`.
- Microsoft ODBC Driver 17 o 18 for SQL Server.
- Composer 2.
- SQL Server con una cuenta que pueda crear bases de datos y hacer respaldos. Con autenticación de Windows en su propio equipo esto normalmente ya se cumple.
- SQL Server Management Studio (SSMS) para ejecutar los scripts.
- MongoDB escuchando en `127.0.0.1:27017`, u otro servidor configurado en `.env`. Si MongoDB no está disponible, la pantalla de cocina sigue mostrando los pedidos desde SQL Server en modo de solo lectura.

## 6. Instalación de dependencias

```bash
composer install
```

El proyecto no necesita Node.js: los estilos de la plantilla ya vienen compilados en `public/src/assets/css/theme.css`.

## 7. Configuración del archivo `.env`

```bash
copy .env.example .env        # Linux o Mac: cp .env.example .env
php artisan key:generate
```

Variables importantes:

| Variable | Para qué sirve |
|---|---|
| `APP_NAME` | Nombre comercial que aparece en pantallas, ticket y respaldos. Por defecto: La Cuchara de Luison |
| `APP_DEBUG` | `false` en producción, para no mostrar detalles de errores |
| `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Conexión a SQL Server. Para una instancia con nombre use `EQUIPO\INSTANCIA`. Deje usuario y contraseña vacíos para usar autenticación de Windows |
| `DB_BACKUP_PATH` | Carpeta del servidor SQL Server donde se guardan los `.bak`. Si queda vacía, se usa la carpeta de respaldos configurada en SQL Server |
| `DB_IMPERSONAR_ROLES` | `true` para que cada petición de mesero, cocina o caja se ejecute con el rol de SQL Server correspondiente (requiere permiso IMPERSONATE) |
| `SESION_UNICA_MINUTOS` | Minutos sin actividad tras los que una sesión abandonada libera la cuenta |
| `DB_MONGO_HOST`, `DB_MONGO_PORT`, `DB_MONGO_DATABASE` | Conexión a MongoDB |
| `DB_MONGO_TIMEOUT_MS` | Cuánto espera la aplicación a MongoDB antes de usar el modo de respaldo (2000 ms por defecto) |

No suba el archivo `.env` al repositorio, porque contiene la llave de la aplicación y los datos de conexión.

## 8. Configuración de SQL Server

1. Habilite la autenticación que vaya a usar: Windows, o mixta si va a usar usuario y contraseña.
2. Si la aplicación se conecta por TCP, habilite TCP/IP en *SQL Server Configuration Manager*.
3. La cuenta de conexión de la aplicación necesita crear la base, hacer respaldos y, para restaurar desde la aplicación, permiso de `dbcreator` o `sysadmin`.

## 9. Creación y configuración de la base de datos

El script `1_RestauranteDB_estructura.sql` crea la base `RestauranteDB` si no existe. También activa `ALLOW_SNAPSHOT_ISOLATION` y `READ_COMMITTED_SNAPSHOT`. Con eso, las pantallas que se consultan cada 5 segundos no se bloquean con los cobros.

## 10. Ejecución de los scripts SQL

En SSMS, abra y ejecute **en este orden** los archivos de `database/sql/`:

1. `1_RestauranteDB_estructura.sql`: crea la base de datos, las 24 tablas con sus restricciones e índices.
2. `2_RestauranteDB_programacion.sql`: vistas, triggers, funciones, procedimientos, roles de SQL Server y permisos.
3. `3_datos_iniciales.sql`: catálogos, menú de ejemplo, catálogo de permisos y un usuario de prueba por rol.

Los scripts son de **instalación limpia**: se ejecutan una sola vez, en un servidor donde todavía no existe `RestauranteDB`. Para reinstalar, borre antes la base (`DROP DATABASE RestauranteDB`). El script 3 crea el catálogo de permisos y asigna los permisos iniciales a los roles Administrador, Mesero, Cocinero y Cajero. El único objeto que se crea fuera de la base es `sp_restaurar_restaurante`, que vive en `master` y usa `CREATE OR ALTER` para que una reinstalación no falle.

Después cree los índices de MongoDB:

```bash
php artisan restaurante:mongo-indices
```

## 11. Configuración de respaldos

- Escriba la carpeta en `.env`, por ejemplo `DB_BACKUP_PATH=C:\RespaldosRestaurante\`. Es una carpeta **del equipo donde corre SQL Server**. Si no existe, SQL Server la crea al generar el primer respaldo.
- La cuenta del servicio de SQL Server (por ejemplo `NT Service\MSSQL$MSSQLSERVER01`) debe tener permiso de escritura en esa carpeta.
- Desde **Control → Respaldos** se generan respaldos `.bak` con `CHECKSUM`, que se verifican con `RESTORE VERIFYONLY` y quedan registrados en `RESPALDO_BD`.
- El botón **Restaurar base de datos** pide la contraseña del administrador y una confirmación. Antes de restaurar guarda automáticamente un respaldo del estado actual. Solo restaura SQL Server: MongoDB no cambia.

## 12. Configuración de usuarios y roles

Los usuarios de prueba creados por el script 3 son `admin@restaurante.cr`, `mesero@restaurante.cr`, `cocina@restaurante.cr` y `cajero@restaurante.cr`. Asigne a cada uno su propia contraseña antes de usar el sistema:

```bash
php artisan restaurante:contrasena admin@restaurante.cr
```

El comando pide la contraseña nueva, la guarda como hash bcrypt y activa la cuenta.

Para crear roles nuevos entre en **Personal → Roles y permisos → Nuevo rol**, escriba el nombre y marque los permisos. Un rol se puede eliminar solo si no tiene empleados. El sistema no permite quitar el permiso «Editar roles y asignar permisos» si eso dejaría a nadie capaz de administrar roles.

Si un rol tiene un único permiso de operación (`mesero.usar`, `cocina.usar` o `caja.usar`), al crear un empleado con ese rol se piden los datos del puesto: zona, estación o código de caja.

## 13. Permisos disponibles

| Módulo | Permisos |
|---|---|
| Dashboard | `dashboard.ver` |
| Roles, Empleados, Categorías, Estaciones, Productos, Insumos, Recetas, Mesas, Clientes, Métodos de pago | `<módulo>.ver`, `.crear`, `.editar`, `.eliminar` (por ejemplo `productos.crear`) |
| Turnos de caja | `turnos.ver`, `turnos.crear`, `turnos.editar` |
| Pedidos | `pedidos.ver`, `pedidos.anular` |
| Facturas | `facturas.ver`, `facturas.anular` |
| Pagos, Reportes, Auditoría | `pagos.ver`, `reportes.ver`, `auditoria.ver` |
| Alertas de stock | `alertas.ver`, `alertas.atender` |
| Respaldos | `respaldos.ver`, `respaldos.generar`, `respaldos.restaurar` |
| Operación | `mesero.usar`, `cocina.usar`, `caja.usar`, `caja.supervisar` (cobrar y consultar en nombre de cualquier cajero) |

Cómo se aplican los permisos:

- **En Laravel:** cada ruta declara su permiso con el middleware `can:` (ver `routes/web.php`). Si alguien entra por la URL sin permiso recibe 403. El menú y los botones se muestran según esos mismos permisos (`App\Support\Acceso`).
- **En SQL Server:** las tablas `PERMISO` y `ROL_PERMISO`, la función `fn_tiene_permiso` y la vista `vw_permisos_empleado`. Los procedimientos `sp_registrar_pedido`, `sp_cobrar_cuenta` y `sp_anular_pedido` rechazan a empleados sin el permiso necesario, aunque la llamada no venga de la aplicación.

## 14. Estructura general del proyecto

```
app/
  Console/Commands/      comandos: contraseña, índices de MongoDB, kit de pruebas de SQL Server
  Http/Controllers/      un controlador por módulo (panel, salón, cocina, caja)
  Http/Middleware/       sesión única, identificación del usuario en SQL Server, rol de SQL Server por módulo
  Models/                modelos Eloquent (SQL Server) y *NoSql (MongoDB)
  Support/               Sp (llamadas a procedimientos), SesionUnica, Acceso (permisos por ruta)
database/sql/            los tres scripts de SQL Server
docs/                    documento del proyecto e inventario de iconos y licencias
public/                  CSS y JS del sistema, plantilla MaterialM, imágenes
resources/views/         vistas Blade (layouts de la plantilla, panel, salón, cocina, caja)
routes/web.php           rutas con su permiso
tests/                   pruebas automáticas
```

## 15. Módulos y funcionalidades

- **Panel administrativo:** dashboard; catálogos de categorías, estaciones, productos con foto, insumos, recetas, mesas, clientes y métodos de pago; empleados; roles y permisos; pedidos; facturas; pagos; turnos de caja; reporte de ventas; auditoría de pedidos; alertas de stock; respaldos y restauración.
- **Salón (mesero):** mapa de mesas, toma de pedidos y rondas adicionales, entrega.
- **Cocina:** cola de comandas en tiempo real con cambio de estado.
- **Caja:** cobro con varios métodos de pago, descuento, ticket, facturas emitidas y cierre de turno.
- **Seguridad:**
  - Una sola sesión activa por cuenta, y bloqueo de pestañas duplicadas en el mismo navegador.
  - Límite de intentos de inicio de sesión.
  - Protección CSRF y contraseñas guardadas con bcrypt.

## 16. Procedimiento para iniciar el sistema

```bash
php artisan serve
```

Abra <http://localhost:8000> e inicie sesión.

Para un uso con varios equipos a la vez (producción), sirva el sistema con IIS, Apache o Nginx con PHP-FPM, active OPcache y ejecute:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

En las pruebas de carga de esta auditoría, esa configuración casi triplicó las solicitudes atendidas por segundo.

## 17. Procedimiento para ejecutar pruebas

Pruebas automáticas, que no necesitan SQL Server ni MongoDB porque usan una base SQLite temporal:

```bash
php artisan test
```

Pruebas reales sobre SQL Server: concurrencia, niveles de aislamiento, estrés, respaldos, restauración y fallos. Trabajan sobre una base aparte (`RestauranteDB_Pruebas`), que el kit borra y vuelve a crear con los tres scripts. Nunca modifican `RestauranteDB`:

```bash
php artisan restaurante:pruebas-sqlserver
php artisan restaurante:pruebas-sqlserver --solo=concurrencia,aislamiento
php artisan restaurante:pruebas-sqlserver --usuarios=5,10,25 --segundos=10
```

El informe queda en `storage/app/pruebas/informe-sqlserver-<fecha>.md` (y `.json`). La ejecución completa tarda entre 10 y 20 minutos según el equipo.

## 18. Consideraciones de seguridad

- Use `APP_DEBUG=false` y HTTPS en producción.
- Cambie las contraseñas de los usuarios de prueba antes de usar el sistema con datos reales.
- La cuenta de conexión de SQL Server debe tener solo los permisos necesarios. Active `DB_IMPERSONAR_ROLES=true` si quiere que SQL Server aplique los permisos de cada rol en cada petición.
- Guarde los respaldos fuera de la carpeta `public` (la aplicación lo exige) y copie los `.bak` a otro equipo o a la nube con regularidad.
- Las fotos que suban los usuarios deben ser propias o tener licencia (ver `docs/ICONOS_Y_LICENCIAS.md`).

## 19. Solución de problemas comunes

| Problema | Solución |
|---|---|
| `could not find driver` | Instale las extensiones `pdo_sqlsrv` y `sqlsrv` para su versión de PHP y el ODBC Driver for SQL Server |
| `Login failed for user` | Revise `DB_USERNAME` y `DB_PASSWORD`, o déjelos vacíos para autenticación de Windows |
| Error al generar respaldo: `Operating system error 5` | La cuenta del servicio de SQL Server no tiene permiso de escritura en `DB_BACKUP_PATH` |
| `Tu rol no tiene permisos asignados` | Ejecute `3_datos_iniciales.sql` o asigne permisos al rol desde Roles y permisos |
| `Esta cuenta ya tiene una sesión abierta` | Cierre la otra sesión con «Salir», pida a un administrador que la cierre desde Empleados, o espere `SESION_UNICA_MINUTOS` |
| La cocina muestra «MongoDB no está disponible» | Inicie el servicio de MongoDB. Mientras tanto la cola se ve desde SQL Server en modo de solo lectura |
| `Too Many Attempts` al iniciar sesión | Hubo demasiados intentos desde la misma dirección. Espere un minuto |
| Los iconos o gráficos no aparecen | El equipo no tiene Internet: los iconos y Chart.js se cargan desde CDN. Vea la recomendación en `docs/ICONOS_Y_LICENCIAS.md` |
