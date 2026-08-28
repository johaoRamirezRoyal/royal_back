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

    // Un bloque por tenant (connection) — cada colegio tiene sus propias terminales
    // físicas, así que no pueden compartir un único HIKVISION_HOST global. La clave debe
    // coincidir con el nombre de connection real (ver config/database.php y
    // BasesDatosService::CONNECTIONS): 'mysql' = Royal, 'sami_hebreo' = Hebreo Union.
    // hikvisionattendanceService::configParaTenant() resuelve cuál bloque usar según
    // `database.default` vigente (usuario logueado) o, para el webhook público de
    // /pushNotification que no tiene JWT, resolverTenantPorIp() busca en TODOS los
    // bloques cuál terminal tiene esa IP antes de saber a qué tenant pertenece.
    'hikvision' => [
        'device_type' => 'DS-K1T321MFWX-B',

        'mysql' => [
            'protocol' => env('HIKVISION_PROTOCOL', 'http'),
            'host' => env('HIKVISION_HOST'),
            'port' => env('HIKVISION_PORT', 8000),
            'username' => env('HIKVISION_USERNAME'),
            'password' => env('HIKVISION_PASSWORD'),
            // Terminales adicionales (fan-out), mismas credenciales de arriba.
            // Formato: "Nombre@host[:port],Nombre2@host2[:port2]" - nombre y puerto
            // opcionales (puerto cae al de arriba). Vacío/ausente = un solo
            // dispositivo (comportamiento original, sin cambios para Royal).
            'extra_hosts' => env('HIKVISION_HOSTS'),
            // IP(s) pública(s) (WAN/NAT) desde las que el colegio le habla al webhook
            // entrante /pushNotification — separado de host/extra_hosts (esas son las
            // IPs LAN del terminal, usadas para las llamadas salientes ISAPI). El
            // webhook llega con la IP pública del router del colegio, no la interna.
            'webhook_ips' => env('HIKVISION_WEBHOOK_IPS'),
        ],

        // Hebreo Union: sin terminal física todavía — las variables quedan vacías a
        // propósito (no inventar una IP) hasta que se instale un equipo real. Con
        // HIKVISION_HEBREO_HOST vacío, hikvisionattendanceService no expone ningún
        // dispositivo para este tenant (falla explícito, no un host inventado).
        'sami_hebreo' => [
            'protocol' => env('HIKVISION_HEBREO_PROTOCOL', 'http'),
            'host' => env('HIKVISION_HEBREO_HOST'),
            'port' => env('HIKVISION_HEBREO_PORT', 8000),
            'username' => env('HIKVISION_HEBREO_USERNAME'),
            'password' => env('HIKVISION_HEBREO_PASSWORD'),
            'extra_hosts' => env('HIKVISION_HEBREO_HOSTS'),
            'webhook_ips' => env('HIKVISION_HEBREO_WEBHOOK_IPS'),
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
];
