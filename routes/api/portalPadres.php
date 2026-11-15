<?php

use App\Http\Controllers\PortalPadres\PortalPadresController;
use Illuminate\Support\Facades\Route;

// Autoservicio del acudiente (tipo ACUDIENTE): sin opción; el controller rechaza a los demás.
Route::get('/hijos', [PortalPadresController::class, 'hijos']);
// Llegadas tarde y asistencia de un hijo; el controller valida el vínculo en estudiantes_padres.
Route::get('/hijos/{idEstudiante}/semana', [PortalPadresController::class, 'semana'])->whereNumber('idEstudiante');
// Fotos del curso del hijo, por mes (?mes=YYYY-MM) o búsqueda (?q=); y el resumen del año para el calendario.
Route::get('/hijos/{idEstudiante}/fotos', [PortalPadresController::class, 'fotos'])->whereNumber('idEstudiante');
Route::get('/hijos/{idEstudiante}/fotos/calendario', [PortalPadresController::class, 'calendarioFotos'])->whereNumber('idEstudiante');
