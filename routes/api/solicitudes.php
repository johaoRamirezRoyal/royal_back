<?php

use App\Http\Controllers\ProcesoCompra\SolicitudesController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SolicitudesController::class, 'listar']);
Route::get('/mias', [SolicitudesController::class, 'misSolicitudes']);
Route::get('/seguimiento', [SolicitudesController::class, 'seguimiento']);
Route::get('/{id}', [SolicitudesController::class, 'ver'])->where('id', '[0-9]+');
Route::post('/', [SolicitudesController::class, 'crear']);
Route::post('/{id}/cancelar', [SolicitudesController::class, 'cancelar'])->where('id', '[0-9]+');
Route::post('/{id}/verificar', [SolicitudesController::class, 'verificar'])->where('id', '[0-9]+');
Route::post('/{id}/aprobar', [SolicitudesController::class, 'aprobar'])->where('id', '[0-9]+');
Route::post('/{id}/rechazar-inicial', [SolicitudesController::class, 'rechazarInicial'])->where('id', '[0-9]+');
Route::post('/{id}/asignar-proveedor', [SolicitudesController::class, 'asignarProveedor'])->where('id', '[0-9]+');
Route::post('/{id}/disponible-stock', [SolicitudesController::class, 'disponibleStock'])->where('id', '[0-9]+');
Route::put('/{id}/aplazar', [SolicitudesController::class, 'aplazar'])->where('id', '[0-9]+');
Route::put('/{id}/rechazar', [SolicitudesController::class, 'rechazar'])->where('id', '[0-9]+');
Route::post('/{id}/anular', [SolicitudesController::class, 'anular'])->where('id', '[0-9]+');
Route::post('/{id}/verificar-entrega', [SolicitudesController::class, 'verificarEntrega'])->where('id', '[0-9]+');
Route::post('/{id}/agregar-inventario', [SolicitudesController::class, 'agregarInventario'])->where('id', '[0-9]+');