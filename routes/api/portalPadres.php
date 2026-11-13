<?php

use App\Http\Controllers\PortalPadres\PortalPadresController;
use Illuminate\Support\Facades\Route;

// Autoservicio del acudiente (tipo ACUDIENTE): sin opción; el controller rechaza a los demás.
Route::get('/hijos', [PortalPadresController::class, 'hijos']);
// Llegadas tarde y asistencia de un hijo; el controller valida el vínculo en estudiantes_padres.
Route::get('/hijos/{idEstudiante}/semana', [PortalPadresController::class, 'semana'])->whereNumber('idEstudiante');
