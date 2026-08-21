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
 *   id_periodo_academico (int, opcional)    — sin id_alumno: default período activo.
 *                                              Con id_alumno y sin este parámetro: no se
 *                                              filtra, se listan TODOS sus períodos.
 *   id_alumno            (int, opcional)    — filtrar por alumno (historial completo)
 *   fecha                (Y-m-d, opcional)  — filtrar por día exacto. Sin la opción 99
 *                                              (acceso completo) se ignora: siempre se
 *                                              fuerza hoy.
 * Cada registro trae total_llegadas_tarde_periodo: cuántas (no revocadas) lleva ESE
 * alumno en SU período (id_periodo_academico de la fila), y periodo_academico con
 * id/nombre/fecha_inicio/fecha_fin/activo del período en que se registró.
 * Orden: desc por fecha/hora (más recientes primero). Sin id_alumno puntual, se
 * colapsa a una fila por alumno (la más reciente) — pasar id_alumno para su historial
 * completo (todos los períodos, salvo que también se pase id_periodo_academico).
 */
Route::get('/', [LlegadasTardeController::class, 'obtenerLlegadasTarde']);

/**
 * GET /api/llegadas-tarde/dashboard
 * Query params:
 *   id_periodo_academico (int, opcional) — default: período académico activo
 * Resumen del período: totales, configuración vigente, top 10 estudiantes con más
 * llegadas tarde, desglose por curso y por día (para gráficas). Todas las métricas
 * excluyen llegadas tarde revocadas, salvo resumen.total_llegadas_revocadas (cuántas
 * se revocaron en el período, informativo).
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
 * POST /api/llegadas-tarde/{id}/reenviar-correo
 * Reintenta la notificación (al estudiante y a sus acudientes) de una llegada tarde ya
 * registrada y actualiza `enviado` según el resultado. No repite el aviso a
 * Vicerrectoría aunque el registro tenga `limite_alcanzado`.
 */
Route::post('/{id}/reenviar-correo', [LlegadasTardeController::class, 'reenviarCorreo']);

/**
 * PUT /api/llegadas-tarde/{id}/observacion
 * Body (JSON): { "observacion": "texto libre" | null }
 */
Route::put('/{id}/observacion', [LlegadasTardeController::class, 'actualizarObservacion']);

/**
 * PUT /api/llegadas-tarde/{id}/revocar
 * Body (JSON, opcional): { "observacion": "motivo de la revocación" }
 * Marca la llegada tarde como revocada: se conserva el registro pero deja de contar
 * para total_llegadas_tarde_periodo y para el límite. Requiere acceso completo (99).
 * Si se manda observacion, reemplaza la observación existente del registro.
 */
Route::put('/{id}/revocar', [LlegadasTardeController::class, 'revocarLlegadaTarde']);

/**
 * Configuración estándar (hora límite y cantidad límite de llegadas tarde).
 * PUT body opcional: { "hora_limite": "07:15", "cantidad_limite": 5, "notificar_coordinador": true }
 * notificar_coordinador (perfil 26) solo aplica si hora_limite cambió; es opt-in, no se envía por defecto.
 */
Route::get('/configuracion', [LlegadasTardeConfigController::class, 'index']);
Route::put('/configuracion', [LlegadasTardeConfigController::class, 'update']);