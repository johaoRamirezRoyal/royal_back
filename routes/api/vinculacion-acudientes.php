<?php

use App\Http\Controllers\Estudiantes\VinculacionAcudienteController;
use Illuminate\Support\Facades\Route;

// Acudientes paginados: ?s=&page=&per-page=&sort=&dir=
Route::get('/', [VinculacionAcudienteController::class, 'index']);
// Estudiantes activos paginados: ?s=&cursos[]=&page=&per-page=
Route::get('/estudiantes-buscar', [VinculacionAcudienteController::class, 'buscarEstudiantes']);
Route::get('/{idAcudiente}/estudiantes', [VinculacionAcudienteController::class, 'estudiantes'])->where('idAcudiente', '[0-9]+');
// { "id_acudiente": 10, "id_estudiante": 20 }
Route::post('/', [VinculacionAcudienteController::class, 'vincular']);
Route::delete('/', [VinculacionAcudienteController::class, 'desvincular']);
// multipart: archivo (.xlsx/.xls/.csv) — col A documento acudiente, col B documento estudiante, fila 1 encabezados
Route::post('/importar', [VinculacionAcudienteController::class, 'importar']);
