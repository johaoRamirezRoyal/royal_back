<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('clear', function () {

    $this->info('Limpiando rutas...');
    Artisan::call('route:clear');

    $this->info('Limpiando configuración...');
    Artisan::call('config:clear');

    $this->info('Limpiando cache...');
    Artisan::call('cache:clear');

    $this->info('Optimize Clear... ');
    Artisan::call('optimize:clear');

    $this->info('Limpiando el cache de config... ');
    Artisan::call('config:cache');

    $this->info('✅ Todo limpio correctamente');
})->purpose('Limpia cache, rutas y configuración');
