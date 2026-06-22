<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('hikvision:wipe-all {--force}', function () {
    $hikvisionService = app(\App\Services\Hikvisionattendance\hikvisionattendanceService::class);

    $listado = $hikvisionService->obtenerEmpleadosRegistrados();

    if ($listado['error']) {
        $this->error('No se pudo obtener el listado del dispositivo: '.$listado['message']);
        return 1;
    }

    $empleados = $listado['data'];
    $total = count($empleados);

    if ($total === 0) {
        $this->info('El dispositivo no tiene usuarios registrados.');
        return 0;
    }

    $this->warn("Se encontraron {$total} usuarios en el dispositivo (incluye cuentas manuales/admin si las hay):");
    foreach ($empleados as $empleado) {
        $this->line(" - {$empleado['employeeNo']}: ".($empleado['name'] ?: '(sin nombre)'));
    }

    if (! $this->option('force') && ! $this->confirm("¿Confirmas ELIMINAR estos {$total} usuarios del dispositivo? Esta acción es irreversible.")) {
        $this->info('Operación cancelada.');
        return 0;
    }

    $resultado = $hikvisionService->eliminarTodosLosUsuariosDelDispositivo();

    if ($resultado['error']) {
        $this->error('Error al eliminar: '.$resultado['message']);
        return 1;
    }

    \App\Models\Usuarios\Usuario::where('asistenciaRegistrada', true)
        ->update(['asistenciaRegistrada' => false]);

    $this->info($resultado['message']);
    return 0;
})->purpose('Elimina TODOS los usuarios registrados en el dispositivo Hikvision (incluye cuentas manuales/admin). Acción destructiva e irreversible — solo para limpiar registros erróneos.');

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
