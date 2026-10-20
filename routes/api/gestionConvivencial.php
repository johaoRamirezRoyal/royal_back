<?php

use App\Http\Controllers\GestionAcademica\ObservacionEstudianteController;
use Illuminate\Support\Facades\Route;
use App\Support\Permiso;

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
// Entra quien tenga 133/134/135/99 o sea docente (3/14); qué puede hacer adentro lo decide el controller.
Route::prefix('/observaciones')->middleware(Permiso::ruta([Permiso::OBSERVACIONES, Permiso::OBSERVACIONES_VER_TODAS, Permiso::OBSERVACIONES_SOLO_CONSULTA, Permiso::GESTION_ACADEMICA], [Permiso::PERFIL_DOCENTE, Permiso::PERFIL_DOCENTE_ICT]))->group(function () {
    Route::get('/catalogos', [ObservacionEstudianteController::class, 'catalogos']);
    Route::get('/estudiantes', [ObservacionEstudianteController::class, 'estudiantes']);
    Route::get('/estudiantes/{idEstudiante}/acta', [ObservacionEstudianteController::class, 'acta'])->whereNumber('idEstudiante');
    Route::get('/', [ObservacionEstudianteController::class, 'index']);
    Route::post('/', [ObservacionEstudianteController::class, 'store']);
    Route::put('/{id}', [ObservacionEstudianteController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [ObservacionEstudianteController::class, 'destroy'])->whereNumber('id');
});
