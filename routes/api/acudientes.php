<?php

use App\Http\Controllers\Acudientes\AcudientesController;
use App\Support\Permiso;
use Illuminate\Support\Facades\Route;

// Módulo Acudientes (opción 175). Vincular hijos: /vinculacion-acudientes.
Route::middleware(Permiso::ruta([Permiso::ACUDIENTES]))->group(function () {
    Route::get('/', [AcudientesController::class, 'index']);
    Route::post('/', [AcudientesController::class, 'store']);
    Route::put('/estado', [AcudientesController::class, 'estado']);
    Route::put('/{id}', [AcudientesController::class, 'update'])->whereNumber('id');
    Route::post('/importar', [AcudientesController::class, 'importar']);
    Route::post('/{id}/acceso-portal', [AcudientesController::class, 'accesoPortal'])->whereNumber('id');
});
