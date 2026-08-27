<?php

use App\Http\Controllers\AdminManagement\BasesDatosController;
use App\Http\Controllers\AdminManagement\LogDominioController;
use Illuminate\Support\Facades\Route;

Route::prefix('bases-datos')->group(function () {
    Route::get('/', [BasesDatosController::class, 'listar']);
    Route::put('/', [BasesDatosController::class, 'renombrar']);
    Route::delete('/nombre', [BasesDatosController::class, 'restablecerNombre']);
    Route::get('/conexion-activa', [BasesDatosController::class, 'conexionActiva']);
    Route::put('/conexion-activa', [BasesDatosController::class, 'establecerConexionActiva']);
    Route::delete('/conexion-activa', [BasesDatosController::class, 'restablecerConexionActiva']);
});

/**
 * ?dominio=&metodo=&ruta=&fecha_desde=&fecha_hasta=&per-page= (todos opcionales)
 */
Route::prefix('logs-dominio')->group(function () {
    Route::get('/', [LogDominioController::class, 'index']);
    Route::get('/dominios', [LogDominioController::class, 'dominios']);
});
