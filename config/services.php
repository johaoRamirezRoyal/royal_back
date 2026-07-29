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

    'hikvision' => [
        'protocol' => env('HIKVISION_PROTOCOL', 'http'),
        'host' => env('HIKVISION_HOST'),
        'port' => env('HIKVISION_PORT', 8000),
        'username' => env('HIKVISION_USERNAME'),
        'password' => env('HIKVISION_PASSWORD'),
        'device_type' => 'DS-K1T321MFWX-B'
    ],

    'sami' => [
        'base_url' => env('SAMI_BASE_URL', 'https://sami.royalschool.edu.co'),
        'login_path' => env('SAMI_LOGIN_PATH', '/login'),
        'home_path' => env('SAMI_HOME_PATH', '/inicio'),
        'cookie_name' => env('SAMI_SESSION_COOKIE_NAME', 'PHPSESSID'),
        'cache_ttl' => env('SAMI_SSO_CACHE_TTL_MINUTES', 120),
    ],
];
