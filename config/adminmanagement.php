<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Correos con acceso a la administración transversal
    |--------------------------------------------------------------------------
    |
    | Los módulos de administración multi-tenant (marcas por dominio, bases de
    | datos vinculadas, tráfico por dominio, elegir la connection activa) solo
    | son alcanzables para estos correos exactos — allowlist fija, no un
    | dominio de correo. Ver App\Http\Middleware\RestrictToAdminEmails.
    |
    */

    'emails' => array_map(
        fn ($correo) => mb_strtolower(trim($correo)),
        explode(',', env(
            'ADMIN_MANAGEMENT_EMAILS',
            'hernando.ramirez@royalschool.edu.co,angel.vargas@royalschool.edu.co,jhonier.duran@royalschool.edu.co'
        ))
    ),

];
