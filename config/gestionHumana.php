<?php

return [

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
