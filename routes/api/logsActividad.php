<?php

use App\Http\Controllers\LogsActividad\LogsActividadController;
use Illuminate\Support\Facades\Route;

/**
 * ?id_user=&metodo=&ruta=&fecha_desde=&fecha_hasta=&per-page= (todos opcionales)
 */
Route::get('/', [LogsActividadController::class, 'index']);
