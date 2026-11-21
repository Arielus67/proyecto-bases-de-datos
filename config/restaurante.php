<?php

return [

    'impersonar_roles_sql' => (bool) env('DB_IMPERSONAR_ROLES', false),

    'respaldos' => [
        'ruta' => env('DB_BACKUP_PATH'),
    ],

    'intervalo_actualizacion_ms' => 5000,

    'sesion_unica_minutos' => (int) env('SESION_UNICA_MINUTOS', 5),

    // Carpeta dentro de public/ donde se guardan las fotos de los productos
    'carpeta_fotos' => 'img/productos',

];
