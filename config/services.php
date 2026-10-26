<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    // Las terminales (host, puerto, credenciales) ya no van aquí: están en la tabla
    // hikvision_dispositivos de cada colegio y se administran desde "Terminales de acceso".
    'hikvision' => [
        'device_type' => 'DS-K1T321MFWX-B',
        // Contraseña de asistencia solo con dígitos del documento (ver construirPasswordAsistencia).
        'password_numerica' => env('HIKVISION_PASSWORD_NUMERICA', true),
        // Override del mapa perfil→groupId ("16:8,3:9"; "16:" = no enviar groupId para ese perfil).
        'group_ids' => env('HIKVISION_GROUP_IDS'),

        // ponytail: solo lo lee la migración 2026_10_26_100000_add_conexion_to_hikvision_dispositivos
        // para importar las terminales de Royal del .env. Cuando esté corrida en producción, se
        // borra este bloque junto con las variables HIKVISION_HOST/PORT/PROTOCOL/USERNAME/
        // PASSWORD/HOSTS del .env.
        'legado' => [
            'mysql' => [
                'protocol' => env('HIKVISION_PROTOCOL', 'http'),
                'host' => env('HIKVISION_HOST'),
                'port' => env('HIKVISION_PORT', 8000),
                'username' => env('HIKVISION_USERNAME'),
                'password' => env('HIKVISION_PASSWORD'),
                'extra_hosts' => env('HIKVISION_HOSTS'),
            ],
        ],
    ],

    'whatsapp' => [
        // Meta Cloud API (WhatsApp Business). Mientras no haya WABA + token, dejar
        // WHATSAPP_ENABLED en false: WhatsAppService no intenta llamar a la API, solo
        // deja constancia en el log (mismo espíritu que MAIL_MAILER=log para correo).
        'enabled' => env('WHATSAPP_ENABLED', false),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'template_lang' => env('WHATSAPP_TEMPLATE_LANG', 'es'),
        // Nombres de las plantillas ya aprobadas en Meta Business Manager para
        // llegadas tarde (ver LlegadasTarde::plantillaWhatsApp()).
        'template_normal' => env('WHATSAPP_TEMPLATE_NORMAL', 'llegada_tarde_normal'),
        'template_advertencia' => env('WHATSAPP_TEMPLATE_ADVERTENCIA', 'llegada_tarde_advertencia'),
        'template_limite' => env('WHATSAPP_TEMPLATE_LIMITE', 'llegada_tarde_limite'),
    ],

    'sami' => [
        'base_url' => env('SAMI_BASE_URL', 'https://sami.royalschool.edu.co'),
        'login_path' => env('SAMI_LOGIN_PATH', '/login'),
        'home_path' => env('SAMI_HOME_PATH', '/inicio'),
        'cookie_name' => env('SAMI_SESSION_COOKIE_NAME', 'PHPSESSID'),
        'cache_ttl' => env('SAMI_SSO_CACHE_TTL_MINUTES', 120),
    ],
    // Redes desde las que vale marcar asistencia con el QR de portería (CIDRs separados por
    // coma, p. ej. "190.0.0.0/24,10.0.0.0/8"). Vacío = desde cualquier red.
    'asistencia_qr' => [
        'redes' => env('ASISTENCIA_QR_REDES'),
    ],

];
