<?php

use App\Http\Controllers\Cafeteria\EstructuraCafeteriaController;
use App\Http\Controllers\Cafeteria\PersonalCafeteriaController;
use App\Support\Permiso;
use Illuminate\Support\Facades\Route;

// Cafetería, etapa 1: estructura (176) y personal (177). El controller repite el permiso.

Route::get('/catalogos', [EstructuraCafeteriaController::class, 'catalogos'])
    ->middleware(Permiso::ruta([Permiso::CAFETERIA_ESTRUCTURA, Permiso::CAFETERIA_PERSONAL]));

Route::middleware(Permiso::ruta([Permiso::CAFETERIA_ESTRUCTURA]))->controller(EstructuraCafeteriaController::class)->group(function () {
    Route::get('/cafeterias', 'index');
    Route::post('/cafeterias', 'store');
    Route::get('/cafeterias/{cafeteria}', 'show')->whereNumber('cafeteria');
    Route::put('/cafeterias/{cafeteria}', 'update')->whereNumber('cafeteria');
    Route::put('/cafeterias/{cafeteria}/estado', 'estado')->whereNumber('cafeteria');
    Route::put('/cafeterias/{cafeteria}/horarios', 'horariosCafeteria')->whereNumber('cafeteria');
    Route::post('/cafeterias/{cafeteria}/puntos-venta', 'storePuntoVenta')->whereNumber('cafeteria');

    Route::put('/puntos-venta/{puntoVenta}', 'updatePuntoVenta')->whereNumber('puntoVenta');
    Route::put('/puntos-venta/{puntoVenta}/horarios', 'horariosPunto')->whereNumber('puntoVenta');
    Route::put('/puntos-venta/{puntoVenta}/almacenes', 'almacenesPunto')->whereNumber('puntoVenta');
    Route::post('/puntos-venta/{puntoVenta}/terminales', 'storeTerminal')->whereNumber('puntoVenta');
    Route::put('/terminales/{terminal}', 'updateTerminal')->whereNumber('terminal');

    Route::get('/almacenes', 'almacenes');
    Route::post('/almacenes', 'storeAlmacen');
    Route::put('/almacenes/{almacen}', 'updateAlmacen')->whereNumber('almacen');

    Route::post('/roles-operativos', 'storeRol');
    Route::put('/roles-operativos/{rol}', 'updateRol')->whereNumber('rol');
});

Route::middleware(Permiso::ruta([Permiso::CAFETERIA_PERSONAL]))->controller(PersonalCafeteriaController::class)->group(function () {
    Route::get('/personal', 'index');
    Route::get('/personal/candidatos', 'candidatos');
    Route::post('/personal', 'store');
    Route::put('/personal/{asignacion}/finalizar', 'finalizar')->whereNumber('asignacion');
    Route::put('/personal/{asignacion}/revocar', 'revocar')->whereNumber('asignacion');
});
