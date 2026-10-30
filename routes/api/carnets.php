<?php

use App\Http\Controllers\Carnets\CarnetController;
use App\Http\Controllers\Carnets\CarnetPlantillaController;
use App\Support\Permiso;
use Illuminate\Support\Facades\Route;

// Sin opción (autoservicio): el carnet digital del usuario en sesión y las tipografías con que se dibuja.
Route::get('/mio', [CarnetController::class, 'mio']);
Route::get('/fuentes', [CarnetPlantillaController::class, 'fuentes']);
Route::get('/fuentes/{archivo}', [CarnetPlantillaController::class, 'archivoFuente']);

Route::middleware(Permiso::ruta([Permiso::CARNETS]))->group(function () {
    Route::get('/', [CarnetController::class, 'index']);
    Route::post('/emitir', [CarnetController::class, 'emitir']);
    Route::post('/vista-previa', [CarnetController::class, 'vistaPrevia']);
    Route::put('/entregar', [CarnetController::class, 'entregar']);
    Route::put('/{id}/estado', [CarnetController::class, 'cambiarEstado'])->where('id', '[0-9]+');
    Route::get('/usuario/{idUser}/historial', [CarnetController::class, 'historial'])->where('idUser', '[0-9]+');
    Route::post('/usuario/{idUser}/foto', [CarnetController::class, 'subirFoto'])->where('idUser', '[0-9]+');
    Route::post('/fotos', [CarnetController::class, 'subirFotosLote']);

    // Plantillas (como las de cumpleaños): una en uso por tipo.
    Route::prefix('plantillas')->controller(CarnetPlantillaController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::post('/imagen', 'subirImagen');
        Route::get('/{id}', 'show')->whereNumber('id');
        Route::put('/{id}', 'update')->whereNumber('id');
        Route::put('/{id}/estado', 'cambiarEstado')->whereNumber('id');
        Route::post('/{id}/duplicar', 'duplicar')->whereNumber('id');
        Route::delete('/{id}', 'destroy')->whereNumber('id');
    });
});
