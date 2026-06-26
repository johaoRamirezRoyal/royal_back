<?php

use App\Http\Controllers\LlegadasTarde\LlegadasTardeController;
use Illuminate\Support\Facades\Route;

/**
 * POST /api/llegadas-tarde
 * Body (JSON):
 *   {
 *       "id_alumno": 1,
 *       "id_periodo_academico": 1,
 *       "fecha": "2026-06-25",
 *       "hora": "08:30"
 *   }
 */
Route::post('/', [LlegadasTardeController::class, 'agregarLlegadaTarde']);

/**
 * GET /api/llegadas-tarde
 * Query params:
 *   id_periodo_academico (int, requerido)
 *   id_alumno           (int, opcional) — filtrar por alumno
 * Ejemplo: /api/compartido/llegadas-tarde?id_periodo_academico=1&id_alumno=5
 */
Route::get('/', [LlegadasTardeController::class, 'obtenerLlegadasTarde']);

/**
 * DELETE /api/llegadas-tarde
 * Body (JSON):
 *   {
 *       "ids_llegadas_tarde": [1, 2, 3]
 *   }
 */
Route::delete('/', [LlegadasTardeController::class, 'eliminarLlegadaTarde']);
Route::delete('/{id}', [LlegadasTardeController::class, 'eliminarLlegadaTarde']);