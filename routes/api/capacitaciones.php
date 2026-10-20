<?php

use App\Http\Controllers\Capacitaciones\CapacitacionesController;
use Illuminate\Support\Facades\Route;
use App\Support\Permiso;

// Autoservicio: cualquier usuario autenticado (pestaña de /profile). El certificado propio
// tampoco exige opción; el de otro usuario (?id_user=) exige la 93 — ver el controller.
Route::get('/mis-completadas', [CapacitacionesController::class, 'misCompletadas']);

// Capacitaciones realizadas (opción 93)
Route::get('/realizadas', [CapacitacionesController::class, 'realizadas'])->middleware(Permiso::ruta([Permiso::CAPACITACIONES_REALIZADAS]));
Route::get('/realizadas/{idUser}', [CapacitacionesController::class, 'realizadasDeUsuario'])->whereNumber('idUser')->middleware(Permiso::ruta([Permiso::CAPACITACIONES_REALIZADAS]));

// Administración (opción 86)
Route::prefix('/admin')->group(function () {
    Route::get('/cursos', [CapacitacionesController::class, 'listarCursos'])->middleware(Permiso::ruta([Permiso::CAPACITACIONES_CONFIGURAR]));
    Route::post('/cursos', [CapacitacionesController::class, 'guardarCurso'])->middleware(Permiso::ruta([Permiso::CAPACITACIONES_CONFIGURAR]));
    // El frontend envía POST + _method=PUT: PHP no parsea multipart en un PUT real.
    Route::put('/cursos/{id}', [CapacitacionesController::class, 'guardarCurso'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::CAPACITACIONES_CONFIGURAR]));
    Route::patch('/cursos/{id}/estado', [CapacitacionesController::class, 'cambiarEstado'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::CAPACITACIONES_CONFIGURAR]));

    Route::get('/cursos/{id}/modulos', [CapacitacionesController::class, 'modulos'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::CAPACITACIONES_CONFIGURAR]));
    Route::post('/cursos/{id}/modulos', [CapacitacionesController::class, 'crearModulo'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::CAPACITACIONES_CONFIGURAR]));
    Route::put('/modulos/{id}', [CapacitacionesController::class, 'actualizarModulo'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::CAPACITACIONES_CONFIGURAR]));
    Route::delete('/modulos/{id}', [CapacitacionesController::class, 'eliminarModulo'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::CAPACITACIONES_CONFIGURAR]));

    Route::post('/modulos/{id}/contenidos', [CapacitacionesController::class, 'crearContenido'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::CAPACITACIONES_CONFIGURAR]));
    Route::put('/contenidos/{id}', [CapacitacionesController::class, 'actualizarContenido'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::CAPACITACIONES_CONFIGURAR]));
    Route::delete('/contenidos/{id}', [CapacitacionesController::class, 'eliminarContenido'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::CAPACITACIONES_CONFIGURAR]));

    Route::get('/cursos/{id}/quiz', [CapacitacionesController::class, 'quizAdmin'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::CAPACITACIONES_CONFIGURAR]));
    Route::put('/cursos/{id}/quiz', [CapacitacionesController::class, 'guardarQuiz'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::CAPACITACIONES_CONFIGURAR]));

    Route::get('/cursos/{id}/perfiles', [CapacitacionesController::class, 'perfiles'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::CAPACITACIONES_CONFIGURAR]));
    Route::put('/cursos/{id}/perfiles', [CapacitacionesController::class, 'guardarPerfiles'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::CAPACITACIONES_CONFIGURAR]));
});

// Realizar capacitaciones (opción 85; la 86 también puede previsualizar)
Route::get('/', [CapacitacionesController::class, 'disponibles'])->middleware(Permiso::ruta([Permiso::CAPACITACIONES]));
Route::post('/contenidos/{id}/visto', [CapacitacionesController::class, 'marcarVisto'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::CAPACITACIONES, Permiso::CAPACITACIONES_CONFIGURAR]));
Route::get('/{id}', [CapacitacionesController::class, 'detalle'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::CAPACITACIONES, Permiso::CAPACITACIONES_CONFIGURAR]));
Route::get('/{id}/quiz', [CapacitacionesController::class, 'quiz'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::CAPACITACIONES, Permiso::CAPACITACIONES_CONFIGURAR]));
Route::post('/{id}/quiz', [CapacitacionesController::class, 'responderQuiz'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::CAPACITACIONES, Permiso::CAPACITACIONES_CONFIGURAR]));
Route::get('/{id}/certificado', [CapacitacionesController::class, 'certificado'])->whereNumber('id');
