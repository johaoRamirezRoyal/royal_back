<?php

use App\Http\Controllers\Estudiantes\VinculacionAcudienteController;
use Illuminate\Support\Facades\Route;
use App\Support\Permiso;

// Acudientes paginados: ?s=&page=&per-page=&sort=&dir=
Route::get('/', [VinculacionAcudienteController::class, 'index'])->middleware(Permiso::ruta([Permiso::VINCULACION_ACUDIENTES]));
// Estudiantes activos paginados: ?s=&cursos[]=&page=&per-page=
Route::get('/estudiantes-buscar', [VinculacionAcudienteController::class, 'buscarEstudiantes'])->middleware(Permiso::ruta([Permiso::VINCULACION_ACUDIENTES]));
Route::get('/{idAcudiente}/estudiantes', [VinculacionAcudienteController::class, 'estudiantes'])->where('idAcudiente', '[0-9]+')->middleware(Permiso::ruta([Permiso::VINCULACION_ACUDIENTES]));
// Inverso: acudientes vinculados a un estudiante (edición de usuario en /users y, para el
// director de grupo de su curso, la hoja de vida). El controller decide el alcance del docente.
Route::get('/estudiante/{idEstudiante}/acudientes', [VinculacionAcudienteController::class, 'acudientes'])->where('idEstudiante', '[0-9]+')->middleware(Permiso::ruta([Permiso::VINCULACION_ACUDIENTES], Permiso::PERFILES_DOCENTE));
// { "id_acudiente": 10, "id_estudiante": 20 }
Route::post('/', [VinculacionAcudienteController::class, 'vincular'])->middleware(Permiso::ruta([Permiso::VINCULACION_ACUDIENTES]));
Route::delete('/', [VinculacionAcudienteController::class, 'desvincular'])->middleware(Permiso::ruta([Permiso::VINCULACION_ACUDIENTES]));
// multipart: archivo (.xlsx/.xls/.csv) — col A documento acudiente, col B documento estudiante, fila 1 encabezados
Route::post('/importar', [VinculacionAcudienteController::class, 'importar'])->middleware(Permiso::ruta([Permiso::VINCULACION_ACUDIENTES]));
