<?php

use App\Http\Controllers\HistoriaClinica\HistoriaClinicaController;
use Illuminate\Support\Facades\Route;

// ── Cognitivo Lenguaje ──────────────────────────────────────

Route::get('/cognitivo-lenguaje', [HistoriaClinicaController::class, 'verCognitivoLenguaje']);
Route::post('/cognitivo-lenguaje', [HistoriaClinicaController::class, 'añadirCognitivoLenguaje']);
Route::put('/cognitivo-lenguaje', [HistoriaClinicaController::class, 'actualizarCognitivoLenguaje']);
Route::delete('/cognitivo-lenguaje', [HistoriaClinicaController::class, 'eliminarCognitivoLenguaje']);

// ── Desarrollo Psicomotor ───────────────────────────────────

Route::get('/desarrollo-psicomotor', [HistoriaClinicaController::class, 'verDesarrolloPsicomotor']);
Route::post('/desarrollo-psicomotor', [HistoriaClinicaController::class, 'añadirDesarrolloPsicomotor']);
Route::put('/desarrollo-psicomotor', [HistoriaClinicaController::class, 'actualizarDesarrolloPsicomotor']);
Route::delete('/desarrollo-psicomotor', [HistoriaClinicaController::class, 'eliminarDesarrolloPsicomotor']);

// ── Embarazo Parto ──────────────────────────────────────────

Route::get('/embarazo-parto', [HistoriaClinicaController::class, 'verEmbarazoParto']);
Route::post('/embarazo-parto', [HistoriaClinicaController::class, 'añadirEmbarazoParto']);
Route::put('/embarazo-parto', [HistoriaClinicaController::class, 'actualizarEmbarazoParto']);
Route::delete('/embarazo-parto', [HistoriaClinicaController::class, 'eliminarEmbarazoParto']);

// ── Estructura Familiar ─────────────────────────────────────

Route::get('/estructura-familiar', [HistoriaClinicaController::class, 'verEstructuraFamiliar']);
Route::post('/estructura-familiar', [HistoriaClinicaController::class, 'añadirEstructuraFamiliar']);
Route::put('/estructura-familiar', [HistoriaClinicaController::class, 'actualizarEstructuraFamiliar']);
Route::delete('/estructura-familiar', [HistoriaClinicaController::class, 'eliminarEstructuraFamiliar']);

// ── Hermanos ────────────────────────────────────────────────

Route::get('/hermanos', [HistoriaClinicaController::class, 'verHermanos']);
Route::post('/hermanos', [HistoriaClinicaController::class, 'añadirHermano']);
Route::put('/hermanos', [HistoriaClinicaController::class, 'actualizarHermano']);
Route::delete('/hermanos', [HistoriaClinicaController::class, 'eliminarHermano']);

// ── Historia Escolar ────────────────────────────────────────

Route::get('/historia-escolar', [HistoriaClinicaController::class, 'verHistoriaEscolar']);
Route::post('/historia-escolar', [HistoriaClinicaController::class, 'añadirHistoriaEscolar']);
Route::put('/historia-escolar', [HistoriaClinicaController::class, 'actualizarHistoriaEscolar']);
Route::delete('/historia-escolar', [HistoriaClinicaController::class, 'eliminarHistoriaEscolar']);

// ── Observaciones y Firmas ──────────────────────────────────

Route::get('/observaciones-firmas', [HistoriaClinicaController::class, 'verObservacionesFirmas']);
Route::post('/observaciones-firmas', [HistoriaClinicaController::class, 'añadirObservacionesFirmas']);
Route::put('/observaciones-firmas', [HistoriaClinicaController::class, 'actualizarObservacionesFirmas']);
Route::post('/observaciones-firmas/{id}/firma/{campo}', [HistoriaClinicaController::class, 'subirFirma']);
Route::delete('/observaciones-firmas', [HistoriaClinicaController::class, 'eliminarObservacionesFirmas']);

// ── Psicoafectiva ───────────────────────────────────────────

Route::get('/psicoafectiva', [HistoriaClinicaController::class, 'verPsicoafectiva']);
Route::post('/psicoafectiva', [HistoriaClinicaController::class, 'añadirPsicoafectiva']);
Route::put('/psicoafectiva', [HistoriaClinicaController::class, 'actualizarPsicoafectiva']);
Route::delete('/psicoafectiva', [HistoriaClinicaController::class, 'eliminarPsicoafectiva']);

// ── Remisión ────────────────────────────────────────────────

Route::get('/remision', [HistoriaClinicaController::class, 'verRemision']);
Route::post('/remision', [HistoriaClinicaController::class, 'añadirRemision']);
Route::put('/remision', [HistoriaClinicaController::class, 'actualizarRemision']);
Route::delete('/remision', [HistoriaClinicaController::class, 'eliminarRemision']);
