<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Correo(s) de Gestión Humana
    |--------------------------------------------------------------------------
    |
    | Reciben copia de cada solicitud nueva (certificados, trámites y
    | servicios), además del propio solicitante. Allowlist simple por env,
    | mismo patrón que ADMIN_MANAGEMENT_EMAILS (config/adminmanagement.php) —
    | no amerita una tabla de configuración editable para un solo valor.
    |
    */

    'correo_notificacion' => array_filter(array_map(
        fn ($correo) => trim($correo),
        explode(',', env('GESTION_HUMANA_EMAIL_ROYAL', ''))
    )),

    /*
    |--------------------------------------------------------------------------
    | Correos de Dirección Administrativa (Permisos y Licencias)
    |--------------------------------------------------------------------------
    |
    | Notificados en solicitudes/actualizaciones de permiso de niveles/perfiles que no
    | dependen de un coordinador de nivel (ver PermisosLicenciasServices::destinatariosNotificacion,
    | replica el enrutamiento por perfil del legado ControlRecursos::solicitarPermisoControl/estadoPermisoControl).
    |
    */

    'correo_direccion_administrativa' => env('ROYAL_DIRECCION_ADMINISTRATIVA_EMAIL'),
    'correo_asistente_direccion_administrativa' => env('ROYAL_ASISTENTE_DIRECCION_ADMINISTRATIVA_EMAIL'),

];
