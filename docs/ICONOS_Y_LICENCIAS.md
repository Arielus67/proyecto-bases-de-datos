# Inventario de iconos, imágenes y licencias — La Cuchara de Luison

Revisión hecha sobre el código del proyecto (vistas Blade, JavaScript y CSS). Las licencias se verificaron en las fuentes oficiales indicadas en cada fila; no se dio por libre ningún recurso solo por estar en Internet.

## 1. Resumen

| Recurso | Versión | Licencia | Uso comercial | Atribución | Restricciones de redistribución | Estado |
|---|---|---|---|---|---|---|
| Tabler Icons (webfont) | 2.44.0 (CDN jsDelivr) | MIT | Sí | Conservar el aviso de copyright y la licencia MIT si se redistribuyen los archivos | Ninguna adicional | Aprobado |
| Plantilla MaterialM (Tailwind, WrapPixel) | versión gratuita | MIT | Sí | Conservar el aviso de licencia en los archivos de la plantilla | Ninguna adicional | Aprobado |
| Preline UI (`preline.js`) | 3.0.1 (CDN) | MIT + Preline UI Fair Use License | Sí | Conservar los avisos; atribución con enlace al repositorio si se redistribuye | No usarlo para crear un producto que compita con Preline | Aprobado (solo se carga desde CDN, no se redistribuye) |
| Chart.js | 4.4.1 (CDN) | MIT | Sí | Conservar el aviso de copyright | Ninguna adicional | Aprobado |
| Tailwind CSS (compilado en `theme.css`) | 4.1.5 | MIT | Sí | El aviso ya está en la cabecera de `theme.css` | Ninguna adicional | Aprobado |
| Fuente DM Sans (Google Fonts) | — | SIL Open Font License 1.1 | Sí | No exige atribución visible | No se puede vender la fuente sola | Aprobado |
| Fuente JetBrains Mono (Google Fonts) | — | SIL Open Font License 1.1 | Sí | No exige atribución visible | No se puede vender la fuente sola | Aprobado |
| Ilustraciones de platos (`public/img/productos/predeterminadas/*.svg`) | — | Propias del proyecto | Sí | No aplica | No aplica | Aprobado |
| Fondo de pinos (`public/img/fondos/pinos.jpg`) | — | Imagen generada por código para el proyecto | Sí | No aplica | No aplica | Aprobado |
| Favicon de la marca (`public/img/marca/favicon.*`) | — | Propio del proyecto (cuchara) | Sí | No aplica | No aplica | Aprobado. Reemplaza al favicon de MaterialM, que es el logotipo de la plantilla y no debía usarse como identidad comercial |
| Fotos de productos subidas desde el panel (`public/img/productos/*.jpg`) | — | **Desconocida** | **Sin verificar** | — | — | **Revisar**: las cargaron usuarios del sistema. Antes de usar el sistema comercialmente, confirme que son fotos propias o con licencia, o reemplácelas |

No se encontraron Font Awesome, Bootstrap Icons, Lucide ni Material Icons en las vistas. La plantilla trae una copia local de Tabler Icons 1.119 y de Feather Icons (ambas MIT) en `public/src/assets/fonts/`, que el sistema no usa; se conservan porque forman parte de la plantilla original.

## 2. Iconos de Tabler usados (70)

Biblioteca: **Tabler Icons** — <https://tabler.io/icons> — repositorio <https://github.com/tabler/tabler-icons> — licencia MIT, Copyright (c) 2020-2026 Paweł Kuna. Se cargan desde `https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css`.

| Clase CSS | Nombre del icono | Fuente oficial | Dónde se usa |
|---|---|---|---|
| `ti-alert-circle` | alert-circle | [tabler.io/icons/icon/alert-circle](https://tabler.io/icons/icon/alert-circle) | app.js, login, grid |
| `ti-alert-octagon` | alert-octagon | [tabler.io/icons/icon/alert-octagon](https://tabler.io/icons/icon/alert-octagon) | index |
| `ti-alert-triangle` | alert-triangle | [tabler.io/icons/icon/alert-triangle](https://tabler.io/icons/icon/alert-triangle) | app.js, index, menu-lateral |
| `ti-armchair` | armchair | [tabler.io/icons/icon/armchair](https://tabler.io/icons/icon/armchair) | index, login, grid y 2 más |
| `ti-armchair-2` | armchair-2 | [tabler.io/icons/icon/armchair-2](https://tabler.io/icons/icon/armchair-2) | index |
| `ti-armchair-off` | armchair-off | [tabler.io/icons/icon/armchair-off](https://tabler.io/icons/icon/armchair-off) | index |
| `ti-arrow-left` | arrow-left | [tabler.io/icons/icon/arrow-left](https://tabler.io/icons/icon/arrow-left) | emitidas |
| `ti-arrow-right` | arrow-right | [tabler.io/icons/icon/arrow-right](https://tabler.io/icons/icon/arrow-right) | index, login |
| `ti-arrow-up-right` | arrow-up-right | [tabler.io/icons/icon/arrow-up-right](https://tabler.io/icons/icon/arrow-up-right) | index |
| `ti-arrows-horizontal` | arrows-horizontal | [tabler.io/icons/icon/arrows-horizontal](https://tabler.io/icons/icon/arrows-horizontal) | ticket |
| `ti-bell-ringing` | bell-ringing | [tabler.io/icons/icon/bell-ringing](https://tabler.io/icons/icon/bell-ringing) | estado |
| `ti-cash` | cash | [tabler.io/icons/icon/cash](https://tabler.io/icons/icon/cash) | index, login, index y 1 más |
| `ti-cash-off` | cash-off | [tabler.io/icons/icon/cash-off](https://tabler.io/icons/icon/cash-off) | pedidos |
| `ti-category` | category | [tabler.io/icons/icon/category](https://tabler.io/icons/icon/category) | menu-lateral |
| `ti-chart-bar` | chart-bar | [tabler.io/icons/icon/chart-bar](https://tabler.io/icons/icon/chart-bar) | index, menu-lateral |
| `ti-chart-bar-off` | chart-bar-off | [tabler.io/icons/icon/chart-bar-off](https://tabler.io/icons/icon/chart-bar-off) | index |
| `ti-chart-line` | chart-line | [tabler.io/icons/icon/chart-line](https://tabler.io/icons/icon/chart-line) | index |
| `ti-chart-pie` | chart-pie | [tabler.io/icons/icon/chart-pie](https://tabler.io/icons/icon/chart-pie) | login |
| `ti-check` | check | [tabler.io/icons/icon/check](https://tabler.io/icons/icon/check) | mesero.js, grid, estado |
| `ti-chef-hat` | chef-hat | [tabler.io/icons/icon/chef-hat](https://tabler.io/icons/icon/chef-hat) | login |
| `ti-chevron-left` | chevron-left | [tabler.io/icons/icon/chevron-left](https://tabler.io/icons/icon/chevron-left) | menu-lateral, sistema |
| `ti-chevron-right` | chevron-right | [tabler.io/icons/icon/chevron-right](https://tabler.io/icons/icon/chevron-right) | sistema |
| `ti-circle` | circle | [tabler.io/icons/icon/circle](https://tabler.io/icons/icon/circle) | menu-item, enlace |
| `ti-circle-check` | circle-check | [tabler.io/icons/icon/circle-check](https://tabler.io/icons/icon/circle-check) | app.js, index |
| `ti-clock` | clock | [tabler.io/icons/icon/clock](https://tabler.io/icons/icon/clock) | grid |
| `ti-clock-hour-4` | clock-hour-4 | [tabler.io/icons/icon/clock-hour-4](https://tabler.io/icons/icon/clock-hour-4) | menu-lateral |
| `ti-coffee` | coffee | [tabler.io/icons/icon/coffee](https://tabler.io/icons/icon/coffee) | grid |
| `ti-credit-card` | credit-card | [tabler.io/icons/icon/credit-card](https://tabler.io/icons/icon/credit-card) | menu-lateral |
| `ti-database-export` | database-export | [tabler.io/icons/icon/database-export](https://tabler.io/icons/icon/database-export) | index, menu-lateral |
| `ti-database-import` | database-import | [tabler.io/icons/icon/database-import](https://tabler.io/icons/icon/database-import) | index, restaurar |
| `ti-eye` | eye | [tabler.io/icons/icon/eye](https://tabler.io/icons/icon/eye) | login.js, login, actions |
| `ti-eye-off` | eye-off | [tabler.io/icons/icon/eye-off](https://tabler.io/icons/icon/eye-off) | login.js |
| `ti-file-invoice` | file-invoice | [tabler.io/icons/icon/file-invoice](https://tabler.io/icons/icon/file-invoice) | index, emitidas, index y 1 más |
| `ti-file-off` | file-off | [tabler.io/icons/icon/file-off](https://tabler.io/icons/icon/file-off) | emitidas |
| `ti-flame` | flame | [tabler.io/icons/icon/flame](https://tabler.io/icons/icon/flame) | grid, menu-lateral |
| `ti-history` | history | [tabler.io/icons/icon/history](https://tabler.io/icons/icon/history) | menu-lateral |
| `ti-home` | home | [tabler.io/icons/icon/home](https://tabler.io/icons/icon/home) | ticket |
| `ti-info-circle` | info-circle | [tabler.io/icons/icon/info-circle](https://tabler.io/icons/icon/info-circle) | app.js |
| `ti-layout-dashboard` | layout-dashboard | [tabler.io/icons/icon/layout-dashboard](https://tabler.io/icons/icon/layout-dashboard) | menu-lateral |
| `ti-lock` | lock | [tabler.io/icons/icon/lock](https://tabler.io/icons/icon/lock) | caja.js, app.js, login y 1 más |
| `ti-logout` | logout | [tabler.io/icons/icon/logout](https://tabler.io/icons/icon/logout) | index, sesion |
| `ti-mail` | mail | [tabler.io/icons/icon/mail](https://tabler.io/icons/icon/mail) | login |
| `ti-notebook` | notebook | [tabler.io/icons/icon/notebook](https://tabler.io/icons/icon/notebook) | menu-lateral |
| `ti-package` | package | [tabler.io/icons/icon/package](https://tabler.io/icons/icon/package) | productos |
| `ti-packages` | packages | [tabler.io/icons/icon/packages](https://tabler.io/icons/icon/packages) | menu-lateral |
| `ti-pencil` | pencil | [tabler.io/icons/icon/pencil](https://tabler.io/icons/icon/pencil) | actions |
| `ti-photo` | photo | [tabler.io/icons/icon/photo](https://tabler.io/icons/icon/photo) | form, index |
| `ti-player-skip-forward` | player-skip-forward | [tabler.io/icons/icon/player-skip-forward](https://tabler.io/icons/icon/player-skip-forward) | login |
| `ti-plus` | plus | [tabler.io/icons/icon/plus](https://tabler.io/icons/icon/plus) | mesero.js, index, index y 3 más |
| `ti-printer` | printer | [tabler.io/icons/icon/printer](https://tabler.io/icons/icon/printer) | index, ticket |
| `ti-receipt` | receipt | [tabler.io/icons/icon/receipt](https://tabler.io/icons/icon/receipt) | index, menu-lateral, index |
| `ti-receipt-2` | receipt-2 | [tabler.io/icons/icon/receipt-2](https://tabler.io/icons/icon/receipt-2) | index, index, pedidos y 1 más |
| `ti-receipt-off` | receipt-off | [tabler.io/icons/icon/receipt-off](https://tabler.io/icons/icon/receipt-off) | index |
| `ti-rotate-clockwise` | rotate-clockwise | [tabler.io/icons/icon/rotate-clockwise](https://tabler.io/icons/icon/rotate-clockwise) | index |
| `ti-search` | search | [tabler.io/icons/icon/search](https://tabler.io/icons/icon/search) | index, productos |
| `ti-search-off` | search-off | [tabler.io/icons/icon/search-off](https://tabler.io/icons/icon/search-off) | index, productos |
| `ti-shield` | shield | [tabler.io/icons/icon/shield](https://tabler.io/icons/icon/shield) | menu-lateral |
| `ti-shield-check` | shield-check | [tabler.io/icons/icon/shield-check](https://tabler.io/icons/icon/shield-check) | index |
| `ti-shield-lock` | shield-lock | [tabler.io/icons/icon/shield-lock](https://tabler.io/icons/icon/shield-lock) | login |
| `ti-shopping-cart` | shopping-cart | [tabler.io/icons/icon/shopping-cart](https://tabler.io/icons/icon/shopping-cart) | mesero.js, index |
| `ti-soup` | soup | [tabler.io/icons/icon/soup](https://tabler.io/icons/icon/soup) | index, menu-lateral |
| `ti-tools-kitchen-2` | tools-kitchen-2 | [tabler.io/icons/icon/tools-kitchen-2](https://tabler.io/icons/icon/tools-kitchen-2) | index, login, index y 3 más |
| `ti-trash` | trash | [tabler.io/icons/icon/trash](https://tabler.io/icons/icon/trash) | actions |
| `ti-trending-down` | trending-down | [tabler.io/icons/icon/trending-down](https://tabler.io/icons/icon/trending-down) | index |
| `ti-trending-up` | trending-up | [tabler.io/icons/icon/trending-up](https://tabler.io/icons/icon/trending-up) | index |
| `ti-upload` | upload | [tabler.io/icons/icon/upload](https://tabler.io/icons/icon/upload) | form |
| `ti-user-circle` | user-circle | [tabler.io/icons/icon/user-circle](https://tabler.io/icons/icon/user-circle) | menu-lateral |
| `ti-users` | users | [tabler.io/icons/icon/users](https://tabler.io/icons/icon/users) | index, menu-lateral, estado |
| `ti-wifi-off` | wifi-off | [tabler.io/icons/icon/wifi-off](https://tabler.io/icons/icon/wifi-off) | mesero.js |
| `ti-x` | x | [tabler.io/icons/icon/x](https://tabler.io/icons/icon/x) | caja.js, app.js, form y 2 más |

## 3. Fuentes consultadas

- Licencia de Tabler Icons: <https://github.com/tabler/tabler-icons/blob/main/LICENSE>
- Licencia de Preline UI: <https://preline.co/docs/license.html> y `package.json` de preline 3.0.1 en <https://unpkg.com/preline@3.0.1/package.json>
- Licencia de Chart.js 4.4.1: <https://unpkg.com/chart.js@4.4.1/package.json>
- MaterialM gratuita (WrapPixel), licencia MIT: <https://wrappixel.com/templates/materialm-free-tailwind-react-admin-template>
- DM Sans, SIL OFL 1.1: <https://fontsource.org/fonts/dm-sans/about>
- JetBrains Mono, SIL OFL 1.1: <https://github.com/JetBrains/JetBrainsMono/blob/master/OFL.txt>
- SIL Open Font License: <https://openfontlicense.org/>

## 4. Recomendación para funcionar sin Internet

Los iconos, Chart.js, Preline y las fuentes se cargan desde CDN. Si el restaurante pierde Internet, los iconos y gráficos no se verán. Para un entorno sin conexión, descargue esos archivos en `public/` y cambie las URL en `layouts/app.blade.php`, `layouts/operativo.blade.php` y `layouts/partials/head.blade.php`; las licencias anteriores lo permiten conservando los avisos.
