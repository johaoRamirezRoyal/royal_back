<?php

use App\Http\Controllers\Administrativo\AnioEscolarPeriodoController;
use App\Http\Controllers\CatalogosUsuarios\NivelesCatalogoController;
use App\Http\Controllers\CatalogosUsuarios\PerfilesController;
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

// CATÁLOGOS DE USUARIOS (opción 224): CRUD de perfiles y nivel. Nunca se borra, solo se
// activa/inactiva. La lectura para selects de otros módulos sigue en /usuarios/perfiles
// y /usuarios/niveles — este módulo no la reemplaza.
Route::prefix('perfiles')->middleware(Permiso::ruta([Permiso::CATALOGOS_USUARIOS]))->group(function () {
    Route::get('/', [PerfilesController::class, 'index']);
    Route::post('/', [PerfilesController::class, 'store']);
    Route::put('/estado', [PerfilesController::class, 'estado']);
    Route::put('/{id}', [PerfilesController::class, 'update'])->whereNumber('id');
});

Route::prefix('niveles')->middleware(Permiso::ruta([Permiso::CATALOGOS_USUARIOS]))->group(function () {
    Route::get('/', [NivelesCatalogoController::class, 'index']);
    Route::post('/', [NivelesCatalogoController::class, 'store']);
    Route::put('/estado', [NivelesCatalogoController::class, 'estado']);
    Route::put('/{id}', [NivelesCatalogoController::class, 'update'])->whereNumber('id');
});
