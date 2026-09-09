<?php

use App\Http\Controllers\Administrativo\AnioEscolarPeriodoController;
use Illuminate\Support\Facades\Route;

Route::prefix('anios-escolares')->group(function () {
    Route::get('/', [AnioEscolarPeriodoController::class, 'listarAnios']);
    Route::post('/', [AnioEscolarPeriodoController::class, 'crearAnio']);
    Route::put('/{id}/estado', [AnioEscolarPeriodoController::class, 'actualizarEstadoAnio']);
});

Route::prefix('periodos')->group(function () {
    Route::get('/', [AnioEscolarPeriodoController::class, 'listarPeriodos']);
    Route::post('/', [AnioEscolarPeriodoController::class, 'crearPeriodo']);
    Route::put('/{id}', [AnioEscolarPeriodoController::class, 'actualizarPeriodo']);
    Route::put('/{id}/estado', [AnioEscolarPeriodoController::class, 'actualizarEstadoPeriodo']);
    Route::put('/{id}/en-curso', [AnioEscolarPeriodoController::class, 'marcarPeriodoEnCurso']);
});
