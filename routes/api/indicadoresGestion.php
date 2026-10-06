<?php

use App\Http\Controllers\Inventarios\IndicadoresGestionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [IndicadoresGestionController::class, 'listar']);
Route::get('/grafica', [IndicadoresGestionController::class, 'grafica']);
Route::post('/', [IndicadoresGestionController::class, 'crear']);
Route::put('/{id}/analisis', [IndicadoresGestionController::class, 'actualizarAnalisis'])->whereNumber('id');
