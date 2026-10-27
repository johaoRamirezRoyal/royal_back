<?php

use App\Http\Controllers\AdminManagement\BannerInformativoController;
use App\Http\Controllers\AdminManagement\BannerModuloController;
use App\Http\Controllers\AdminManagement\BasesDatosController;
use App\Http\Controllers\AdminManagement\CorreosInstitucionalesController;
use App\Http\Controllers\AdminManagement\LlaveMaestraController;
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

Route::prefix('llaves-maestras')->group(function () {
    Route::get('/', [LlaveMaestraController::class, 'index']);
    Route::post('/', [LlaveMaestraController::class, 'generar']);
    Route::delete('/{id}', [LlaveMaestraController::class, 'revocar']);
});

Route::prefix('banner-informativo')->group(function () {
    Route::get('/', [BannerInformativoController::class, 'obtener']);
    Route::put('/', [BannerInformativoController::class, 'actualizar']);
    Route::post('/imagen', [BannerInformativoController::class, 'subirImagen']);
    // Envía el mensaje YA GUARDADO por correo — acción explícita separada del guardado
    // (ver BannerInformativoService::enviarCorreo), no dispara sola al tocar 'activo'.
    Route::post('/enviar-correo', [BannerInformativoController::class, 'enviarCorreo']);
});

// Un banner por módulo del sidebar (la imagen se sube con banner-informativo/imagen).
Route::prefix('banners-modulo')->group(function () {
    Route::get('/', [BannerModuloController::class, 'listar']);
    Route::put('/', [BannerModuloController::class, 'guardar']);
    Route::delete('/{id}', [BannerModuloController::class, 'eliminar'])->whereNumber('id');
});

// Destinatarios fijos de cada módulo (App\Enums\Mails) en la BD de la connection activa.
Route::prefix('correos-institucionales')->group(function () {
    Route::get('/', [CorreosInstitucionalesController::class, 'listar']);
    Route::post('/', [CorreosInstitucionalesController::class, 'crear']);
    Route::put('/{id}', [CorreosInstitucionalesController::class, 'actualizar']);
    Route::delete('/{id}', [CorreosInstitucionalesController::class, 'eliminar']);
});
