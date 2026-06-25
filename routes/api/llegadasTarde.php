<?php

use App\Http\Controllers\LlegadasTarde\LlegadasTardeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LlegadasTardeController::class, 'obtenerLlegadasTarde']);
Route::delete('/', [LlegadasTardeController::class, 'eliminarLlegadaTarde']);