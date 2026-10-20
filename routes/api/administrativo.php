<?php

use App\Http\Controllers\Administrativo\AnioEscolarPeriodoController;
use Illuminate\Support\Facades\Route;
use App\Support\Permiso;

Route::prefix('anios-escolares')->group(function () {
    Route::get('/', [AnioEscolarPeriodoController::class, 'listarAnios'])->middleware(Permiso::ruta([Permiso::ANIO_ESCOLAR_PERIODOS]));
    Route::post('/', [AnioEscolarPeriodoController::class, 'crearAnio'])->middleware(Permiso::ruta([Permiso::ANIO_ESCOLAR_PERIODOS]));
    Route::put('/{id}/estado', [AnioEscolarPeriodoController::class, 'actualizarEstadoAnio'])->middleware(Permiso::ruta([Permiso::ANIO_ESCOLAR_PERIODOS]));
});

Route::prefix('periodos')->group(function () {
    Route::get('/', [AnioEscolarPeriodoController::class, 'listarPeriodos'])->middleware(Permiso::ruta([Permiso::ANIO_ESCOLAR_PERIODOS]));
    Route::post('/', [AnioEscolarPeriodoController::class, 'crearPeriodo'])->middleware(Permiso::ruta([Permiso::ANIO_ESCOLAR_PERIODOS]));
    Route::put('/{id}', [AnioEscolarPeriodoController::class, 'actualizarPeriodo'])->middleware(Permiso::ruta([Permiso::ANIO_ESCOLAR_PERIODOS]));
    Route::put('/{id}/estado', [AnioEscolarPeriodoController::class, 'actualizarEstadoPeriodo'])->middleware(Permiso::ruta([Permiso::ANIO_ESCOLAR_PERIODOS]));
    Route::put('/{id}/en-curso', [AnioEscolarPeriodoController::class, 'marcarPeriodoEnCurso'])->middleware(Permiso::ruta([Permiso::ANIO_ESCOLAR_PERIODOS]));
});
