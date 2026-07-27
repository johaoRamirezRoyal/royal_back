<?php

use App\Http\Controllers\AsistenciasTrabajador\AsistenciasTrabajadorController;
use Illuminate\Support\Facades\Route;

/**
 * GET /api/asistencias-trabajador
 * Query params:
 *   fecha_inicio (date, opcional)
 *   fecha_fin    (date, opcional)
 *   id_user      (int, opcional) — filtrar por trabajador
 */
Route::get('/', [AsistenciasTrabajadorController::class, 'obtenerLlegadas']);

/**
 * DELETE /api/asistencias-trabajador
 * Body (JSON): { "ids": [1, 2, 3] }
 */
Route::delete('/', [AsistenciasTrabajadorController::class, 'eliminarLlegada']);
Route::delete('/{id}', [AsistenciasTrabajadorController::class, 'eliminarLlegada']);
