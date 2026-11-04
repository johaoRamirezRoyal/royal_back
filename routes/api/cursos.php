<?php

use App\Http\Controllers\Cursos\CursosController;
use App\Support\Permiso;
use Illuminate\Support\Facades\Route;

// Abierto a cualquier usuario autenticado: lo usan los selects de curso de otros módulos.
Route::get('/all', [CursosController::class, 'findAll']);

// Avisos de cierre de año en la página de inicio: promoción (145) o periodos (99).
Route::get('/avisos-cierre', [CursosController::class, 'avisosCierreAnio'])->middleware(Permiso::ruta([Permiso::CURSOS, Permiso::GESTION_ACADEMICA]));

// "Mi curso": autoservicio del director de grupo (perfil docente; su curso lo resuelve el backend).
Route::middleware(Permiso::ruta([], Permiso::PERFILES_DOCENTE))->group(function () {
    Route::get('/mi-curso', [CursosController::class, 'miCurso']);
    Route::put('/mi-curso/repiten', [CursosController::class, 'sugerirRepiten']);
});
// Entregar carnets de su curso: opción 146 (el controller limita a su curso).
Route::put('/mi-curso/carnets/entregar', [CursosController::class, 'entregarCarnetsMiCurso'])->middleware(Permiso::ruta([Permiso::CARNETS_MI_CURSO]));

// Administración de cursos y sus estudiantes (opción 145).
Route::middleware(Permiso::ruta([Permiso::CURSOS]))->group(function () {
    Route::get('/', [CursosController::class, 'listar']);
    Route::post('/', [CursosController::class, 'crear']);
    Route::get('/estudiantes-buscar', [CursosController::class, 'estudiantes']);
    Route::get('/docentes', [CursosController::class, 'docentes']);
    Route::get('/promocion', [CursosController::class, 'previaPromocion']);
    Route::post('/promocion', [CursosController::class, 'promover']);
    Route::get('/promocion/estudiantes', [CursosController::class, 'estudiantesParaPromocion']);
    Route::post('/{id}/estudiantes/nuevo', [CursosController::class, 'crearEstudiante'])->whereNumber('id');
    Route::get('/{id}', [CursosController::class, 'detalle'])->whereNumber('id');
    Route::put('/{id}', [CursosController::class, 'actualizar'])->whereNumber('id');
    Route::get('/{id}/estudiantes', [CursosController::class, 'estudiantes'])->whereNumber('id');
    Route::post('/{id}/estudiantes', [CursosController::class, 'asignarEstudiantes'])->whereNumber('id');
    Route::delete('/{id}/estudiantes/{idEstudiante}', [CursosController::class, 'quitarEstudiante'])->whereNumber(['id', 'idEstudiante']);
});
