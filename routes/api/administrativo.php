<?php

use App\Http\Controllers\Administrativo\AnioEscolarPeriodoController;
use App\Support\Permiso;
use Illuminate\Support\Facades\Route;

Route::prefix('anios-escolares')->group(function () {
    Route::get('/', [AnioEscolarPeriodoController::class, 'listarAnios'])->middleware(Permiso::ruta([Permiso::ANIO_ESCOLAR_PERIODOS]));
    Route::post('/', [AnioEscolarPeriodoController::class, 'crearAnio'])->middleware(Permiso::ruta([Permiso::ANIO_ESCOLAR_PERIODOS]));
    Route::post('/siguiente', [AnioEscolarPeriodoController::class, 'iniciarSiguienteAnio'])->middleware(Permiso::ruta([Permiso::ANIO_ESCOLAR_PERIODOS]));
    Route::put('/{id}/estado', [AnioEscolarPeriodoController::class, 'actualizarEstadoAnio'])->middleware(Permiso::ruta([Permiso::ANIO_ESCOLAR_PERIODOS]));
});

Route::prefix('periodos')->group(function () {
    Route::get('/', [AnioEscolarPeriodoController::class, 'listarPeriodos'])->middleware(Permiso::ruta([Permiso::ANIO_ESCOLAR_PERIODOS]));
    Route::post('/', [AnioEscolarPeriodoController::class, 'crearPeriodo'])->middleware(Permiso::ruta([Permiso::ANIO_ESCOLAR_PERIODOS]));
    // Antes de /{id}: si no, "avanzar" se toma como id.
    Route::put('/avanzar', [AnioEscolarPeriodoController::class, 'avanzarPeriodo'])->middleware(Permiso::ruta([Permiso::ANIO_ESCOLAR_PERIODOS]));
    Route::put('/{id}', [AnioEscolarPeriodoController::class, 'actualizarPeriodo'])->middleware(Permiso::ruta([Permiso::ANIO_ESCOLAR_PERIODOS]));
    Route::put('/{id}/estado', [AnioEscolarPeriodoController::class, 'actualizarEstadoPeriodo'])->middleware(Permiso::ruta([Permiso::ANIO_ESCOLAR_PERIODOS]));
    Route::put('/{id}/en-curso', [AnioEscolarPeriodoController::class, 'marcarPeriodoEnCurso'])->middleware(Permiso::ruta([Permiso::ANIO_ESCOLAR_PERIODOS]));
});
