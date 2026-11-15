<?php

use App\Enums\TipoPerfil;
use App\Http\Controllers\FotosCurso\FotosCursoController;
use App\Support\Permiso;
use Illuminate\Support\Facades\Route;

// Fotos del curso: el docente publica en los cursos de su carga (tipo DOCENTE, sin opción) y la
// 172 ve y borra las de todos. El controller limita al docente a sus cursos.
Route::middleware(Permiso::ruta([Permiso::FOTOS_CURSO_GESTIONAR], [TipoPerfil::DOCENTE]))->group(function () {
    Route::get('/cursos', [FotosCursoController::class, 'cursos']);
    Route::get('/opciones', [FotosCursoController::class, 'opciones']);
    Route::get('/', [FotosCursoController::class, 'index']);
    Route::post('/', [FotosCursoController::class, 'store']);
    Route::delete('/{id}', [FotosCursoController::class, 'destroy'])->whereNumber('id');
});
