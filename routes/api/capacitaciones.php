<?php

use App\Http\Controllers\Capacitaciones\CapacitacionesController;
use Illuminate\Support\Facades\Route;

// Autoservicio: cualquier usuario autenticado (pestaña de /profile). El certificado propio
// tampoco exige opción; el de otro usuario (?id_user=) exige la 93 — ver el controller.
Route::get('/mis-completadas', [CapacitacionesController::class, 'misCompletadas']);

// Capacitaciones realizadas (opción 93)
Route::get('/realizadas', [CapacitacionesController::class, 'realizadas']);
Route::get('/realizadas/{idUser}', [CapacitacionesController::class, 'realizadasDeUsuario'])->whereNumber('idUser');

// Administración (opción 86)
Route::prefix('/admin')->group(function () {
    Route::get('/cursos', [CapacitacionesController::class, 'listarCursos']);
    Route::post('/cursos', [CapacitacionesController::class, 'guardarCurso']);
    // El frontend envía POST + _method=PUT: PHP no parsea multipart en un PUT real.
    Route::put('/cursos/{id}', [CapacitacionesController::class, 'guardarCurso'])->whereNumber('id');
    Route::patch('/cursos/{id}/estado', [CapacitacionesController::class, 'cambiarEstado'])->whereNumber('id');

    Route::get('/cursos/{id}/modulos', [CapacitacionesController::class, 'modulos'])->whereNumber('id');
    Route::post('/cursos/{id}/modulos', [CapacitacionesController::class, 'crearModulo'])->whereNumber('id');
    Route::put('/modulos/{id}', [CapacitacionesController::class, 'actualizarModulo'])->whereNumber('id');
    Route::delete('/modulos/{id}', [CapacitacionesController::class, 'eliminarModulo'])->whereNumber('id');

    Route::post('/modulos/{id}/contenidos', [CapacitacionesController::class, 'crearContenido'])->whereNumber('id');
    Route::put('/contenidos/{id}', [CapacitacionesController::class, 'actualizarContenido'])->whereNumber('id');
    Route::delete('/contenidos/{id}', [CapacitacionesController::class, 'eliminarContenido'])->whereNumber('id');

    Route::get('/cursos/{id}/quiz', [CapacitacionesController::class, 'quizAdmin'])->whereNumber('id');
    Route::put('/cursos/{id}/quiz', [CapacitacionesController::class, 'guardarQuiz'])->whereNumber('id');

    Route::get('/cursos/{id}/perfiles', [CapacitacionesController::class, 'perfiles'])->whereNumber('id');
    Route::put('/cursos/{id}/perfiles', [CapacitacionesController::class, 'guardarPerfiles'])->whereNumber('id');
});

// Realizar capacitaciones (opción 85; la 86 también puede previsualizar)
Route::get('/', [CapacitacionesController::class, 'disponibles']);
Route::post('/contenidos/{id}/visto', [CapacitacionesController::class, 'marcarVisto'])->whereNumber('id');
Route::get('/{id}', [CapacitacionesController::class, 'detalle'])->whereNumber('id');
Route::get('/{id}/quiz', [CapacitacionesController::class, 'quiz'])->whereNumber('id');
Route::post('/{id}/quiz', [CapacitacionesController::class, 'responderQuiz'])->whereNumber('id');
Route::get('/{id}/certificado', [CapacitacionesController::class, 'certificado'])->whereNumber('id');
