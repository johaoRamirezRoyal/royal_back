<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('hikvision:wipe-all {--force} {--device=} {--all}', function () {
    $hikvisionService = app(\App\Services\Hikvisionattendance\hikvisionattendanceService::class);
    $idsConfigurados = $hikvisionService->deviceIds();

    if (! $this->option('device') && ! $this->option('all')) {
        $this->error('Debes indicar --device=<id> o --all. Dispositivos configurados: '.implode(', ', $idsConfigurados));
        return 1;
    }

    $objetivo = $this->option('all') ? $idsConfigurados : [$this->option('device')];
    $dispositivosVaciados = 0;

    foreach ($objetivo as $deviceId) {
        if (! in_array($deviceId, $idsConfigurados, true)) {
            $this->error("Dispositivo desconocido: {$deviceId}");
            continue;
        }

        $this->info("--- Dispositivo: {$deviceId} ---");

        $listado = $hikvisionService->listarEmpleadosDeDispositivo($deviceId);

        if ($listado['error']) {
            $this->error('No se pudo obtener el listado del dispositivo: '.$listado['message']);
            continue;
        }

        $empleados = $listado['data'];
        $total = count($empleados);

        if ($total === 0) {
            $this->info('El dispositivo no tiene usuarios registrados.');
            $dispositivosVaciados++;
            continue;
        }

        $this->warn("Se encontraron {$total} usuarios en el dispositivo (incluye cuentas manuales/admin si las hay):");
        foreach ($empleados as $empleado) {
            $this->line(" - {$empleado['employeeNo']}: ".($empleado['name'] ?: '(sin nombre)'));
        }

        if (! $this->option('force') && ! $this->confirm("¿Confirmas ELIMINAR estos {$total} usuarios del dispositivo {$deviceId}? Esta acción es irreversible.")) {
            $this->info('Operación cancelada para este dispositivo.');
            continue;
        }

        $resultado = $hikvisionService->eliminarTodosLosUsuariosDelDispositivo($deviceId);

        if ($resultado['error']) {
            $this->error('Error al eliminar: '.$resultado['message']);
            continue;
        }

        $this->info($resultado['message']);
        $dispositivosVaciados++;
    }

    // El flag asistenciaRegistrada solo se resetea si TODOS los dispositivos
    // configurados quedaron vacíos: si solo se vació uno de varios, el usuario
    // aún puede marcar en los demás terminales.
    if ($dispositivosVaciados === count($idsConfigurados)) {
        \App\Models\Usuarios\Usuario::where('asistenciaRegistrada', true)
            ->update(['asistenciaRegistrada' => false]);
    } elseif ($dispositivosVaciados > 0) {
        $this->warn('Solo se vaciaron '.$dispositivosVaciados.'/'.count($idsConfigurados).' dispositivos configurados: no se reseteó el flag asistenciaRegistrada (los usuarios aún pueden marcar en los demás terminales).');
    }

    return 0;
})->purpose('Elimina TODOS los usuarios registrados en uno (--device=<id>) o todos (--all) los terminales Hikvision configurados (incluye cuentas manuales/admin). Acción destructiva e irreversible — solo para limpiar registros erróneos.');

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
