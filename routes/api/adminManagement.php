<?php

use App\Http\Controllers\AdminManagement\BasesDatosController;
use App\Http\Controllers\AdminManagement\LogDominioController;
use Illuminate\Support\Facades\Route;

Route::prefix('bases-datos')->group(function () {
    Route::get('/', [BasesDatosController::class, 'listar']);
    Route::put('/', [BasesDatosController::class, 'renombrar']);
    Route::delete('/nombre', [BasesDatosController::class, 'restablecerNombre']);
});

/**
 * ?dominio=&metodo=&ruta=&fecha_desde=&fecha_hasta=&per-page= (todos opcionales)
 */
Route::prefix('logs-dominio')->group(function () {
    Route::get('/', [LogDominioController::class, 'index']);
    Route::get('/dominios', [LogDominioController::class, 'dominios']);
});
