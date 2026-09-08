<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Correo(s) de Gestión Humana
    |--------------------------------------------------------------------------
    |
    | Reciben copia de cada solicitud de certificado nueva, además del propio
    | solicitante. Allowlist simple por env, mismo patrón que
    | ADMIN_MANAGEMENT_EMAILS (config/adminmanagement.php) — no amerita una
    | tabla de configuración editable para un solo valor.
    |
    */

    'correo_gestion_humana' => array_filter(array_map(
        fn ($correo) => trim($correo),
        explode(',', env('GESTION_HUMANA_EMAIL', ''))
    )),

];
