<?php

return [
    // Dispara `migrate:colegios` desde el workflow de deploy (sin SSH al hosting).
    // Vacío = el endpoint no existe (404), mismo criterio que MIGRACION_API_TOKEN.
    'migracion_token' => env('DEPLOY_MIGRACION_TOKEN'),
];
