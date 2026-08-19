<?php

use App\Http\Controllers\ProcesoCompra\SolicitudesController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SolicitudesController::class, 'listar']);
Route::get('/{id}', [SolicitudesController::class, 'ver'])->where('id', '[0-9]+');
Route::post('/', [SolicitudesController::class, 'crear']);
Route::post('/{id}/verificar', [SolicitudesController::class, 'verificar'])->where('id', '[0-9]+');
Route::post('/{id}/asignar-proveedor', [SolicitudesController::class, 'asignarProveedor'])->where('id', '[0-9]+');