<?php

use App\Http\Controllers\LlegadasTarde\LlegadasTardeConfigController;
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
 *   id_periodo_academico (int, opcional)    — default: período académico activo
 *   id_alumno            (int, opcional)    — filtrar por alumno
 *   fecha                (Y-m-d, opcional)  — filtrar por día exacto. Perfil Recepción
 *                                              (33) lo ignora: siempre se fuerza hoy.
 * Cada registro trae total_llegadas_tarde_periodo: cuántas lleva ESE alumno en el
 * período consultado (no el total de todos los alumnos del período).
 */
Route::get('/', [LlegadasTardeController::class, 'obtenerLlegadasTarde']);

/**
 * GET /api/llegadas-tarde/dashboard
 * Query params:
 *   id_periodo_academico (int, opcional) — default: período académico activo
 * Resumen del período: totales, configuración vigente, top 10 estudiantes con más
 * llegadas tarde, desglose por curso y por día (para gráficas).
 */
Route::get('/dashboard', [LlegadasTardeController::class, 'dashboardLlegadasTarde']);

/**
 * DELETE /api/llegadas-tarde
 * Body (JSON):
 *   {
 *       "ids_llegadas_tarde": [1, 2, 3]
 *   }
 */
Route::delete('/', [LlegadasTardeController::class, 'eliminarLlegadaTarde']);
Route::delete('/{id}', [LlegadasTardeController::class, 'eliminarLlegadaTarde']);

/**
 * Configuración estándar (hora límite y cantidad límite de llegadas tarde).
 * PUT body opcional: { "hora_limite": "07:15", "cantidad_limite": 5, "notificar_coordinador": true }
 * notificar_coordinador (perfil 26) solo aplica si hora_limite cambió; es opt-in, no se envía por defecto.
 */
Route::get('/configuracion', [LlegadasTardeConfigController::class, 'index']);
Route::put('/configuracion', [LlegadasTardeConfigController::class, 'update']);