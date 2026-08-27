<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dominio de administración transversal
    |--------------------------------------------------------------------------
    |
    | Los módulos de administración multi-tenant (marcas por dominio, bases de
    | datos vinculadas, tráfico por dominio) solo son alcanzables para usuarios
    | cuyo correo pertenece a este dominio — no depende de por qué Host/URL
    | entra la petición. Ver App\Http\Middleware\RestrictToAdminDomain.
    |
    */

    'domain' => env('ADMIN_MANAGEMENT_DOMAIN', 'gestorsami.adm.co'),

];
