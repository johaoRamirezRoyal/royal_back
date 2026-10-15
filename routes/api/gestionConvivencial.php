<?php

use App\Http\Controllers\GestionAcademica\ObservacionEstudianteController;
use Illuminate\Support\Facades\Route;

/**
 * GESTIÓN CONVIVENCIAL (prefijo /gestion-convivencial).
 *
 * Observaciones de estudiantes por día, en actas por periodo académico. Registran docentes
 * (sus cursos) y opción 133/99 (cualquier estudiante); cada quien ve solo las suyas, la
 * opción 134 ve todas. Listado con contrato DataTable remoto + id_periodo, fecha_inicio,
 * fecha_fin, id_curso, tipo (POSITIVA|NEGATIVA), id_estudiante.
 * POST/PUT: { "id_estudiante" (solo POST), "fecha": "2026-10-14", "tipo": "NEGATIVA",
 *             "motivo": "texto", "id_asignatura": 3 | null }
 * Periodo cerrado (fecha_fin pasada) = solo lectura; solo el autor edita/elimina.
 */
Route::prefix('/observaciones')->group(function () {
    Route::get('/catalogos', [ObservacionEstudianteController::class, 'catalogos']);
    Route::get('/estudiantes', [ObservacionEstudianteController::class, 'estudiantes']);
    Route::get('/estudiantes/{idEstudiante}/acta', [ObservacionEstudianteController::class, 'acta'])->whereNumber('idEstudiante');
    Route::get('/', [ObservacionEstudianteController::class, 'index']);
    Route::post('/', [ObservacionEstudianteController::class, 'store']);
    Route::put('/{id}', [ObservacionEstudianteController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [ObservacionEstudianteController::class, 'destroy'])->whereNumber('id');
});
