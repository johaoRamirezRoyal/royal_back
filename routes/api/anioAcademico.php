<?php

use App\Http\Controllers\AnioAcademico\AnioAcademico;
use App\Http\Controllers\AnioAcademico\PeriodoAcademicoController;
use App\Http\Controllers\AnioAcademico\PeriodoInstitucionalController;
use Illuminate\Support\Facades\Route;

Route::get('/todos', [AnioAcademico::class, 'obtenerAniosAcademicos']);
Route::get('/ultimo', [AnioAcademico::class, 'obtenerUltimoAnioAcademico']);

/**
 * Catálogo institucional de `periodos` (tabla nueva con `en_curso`, no confundir con
 * `periodo-academico` de arriba) — solo lectura, sin gate de opción. El CRUD real vive en
 * /administrativo/periodos (opción 107).
 * GET /periodo/todos?id_anio=&activo=
 * GET /periodo/en-curso
 */
Route::get('/periodo/todos', [PeriodoInstitucionalController::class, 'listar']);
Route::get('/periodo/en-curso', [PeriodoInstitucionalController::class, 'enCurso']);

/**
 * GET /periodo-academico/todos
 * Query params:
 *   id_anio_escolar (int, opcional) — filtrar por año escolar
 *   estado         (bool, opcional, default false) — filtrar por activo
 * Ejemplo: /api/periodo-academico/todos?id_anio_escolar=1&estado=true
 */
Route::get("/periodo-academico/todos", [PeriodoAcademicoController::class, 'obtenerPeriodoAcademico']);

/**
 * GET /periodo-academico/id
 * Query params:
 *   id_anio_escolar (int, requerido)
 * Ejemplo: /api/periodo-academico/id?id_anio_escolar=1
 */
Route::get("/periodo-academico/id", [PeriodoAcademicoController::class, 'obtenerPeriodoAcademicoPorId']);

/**
 * POST /periodo-academico
 * Body (JSON):
{
    "id_anio_escolar": 1,
    "fecha_inicio": "2026-02-01",
    "fecha_fin": "2026-06-30",
    "activo": true
}
 */
Route::post("/periodo-academico", [PeriodoAcademicoController::class, 'agregarPeriodoAcademico']);

/**
 * PUT /periodo-academico/estado
 * Body (JSON):
{
    "ids_periodos_academicos": [1, 2, 3],
    "estado": 0
}
 * Nota: si ids_periodos_academicos se omite, actualiza todos los registros.
 */
Route::put("/periodo-academico/estado", [PeriodoAcademicoController::class, 'desactivarPeriodosAcademicos']);

/**
 * PUT /periodo-academico
 * Body (JSON):
 *   {
 *       "id_periodo_academico": 1,
 *       "id_anio_escolar": 1,
 *       "fecha_inicio": "2026-03-01",
 *       "fecha_fin": "2026-07-31",
 *       "activo": true
 *   }
 */
Route::put("/periodo-academico", [PeriodoAcademicoController::class, 'editarPeriodoAcademico']);

/**
 * DELETE /periodo-academico
 * Body (JSON):
 *   {
 *       "ids_periodo_academico": [1, 2]
 *   }
 */
Route::delete("/periodo-academico", [PeriodoAcademicoController::class, 'eliminarPeriodoAcademico']);
