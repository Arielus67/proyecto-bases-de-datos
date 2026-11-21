<?php

return [

    'impersonar_roles_sql' => (bool) env('DB_IMPERSONAR_ROLES', false),

    'respaldos' => [
        'ruta' => env('DB_BACKUP_PATH'),
    ],

    'intervalo_actualizacion_ms' => 5000,

    'sesion_unica_minutos' => (int) env('SESION_UNICA_MINUTOS', 5),

    // Fondo de caja con el que se abre automáticamente el turno del cajero al iniciar sesión
    'caja_monto_inicial' => (float) env('CAJA_MONTO_INICIAL', 0),

    // Carpeta dentro de public/ donde se guardan las fotos de los productos
    'carpeta_fotos' => 'img/productos',

];
