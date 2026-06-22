<?php
return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000', // React CRA
        'http://localhost:5173', // Vite (MUY IMPORTANTE)
        'http://localhost:5174', // Vite (MUY IMPORTANTE)
        'http://localhost:4000', // Vite (MUY IMPORTANTE)
        'https://frontend-new-s-a-m-i.vercel.app',
        'https://frontend-new-s-a-m-8tnnuns5u-royal-s-projects11.vercel.app',
        'https://gestorsami.royalschool.edu.co',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
