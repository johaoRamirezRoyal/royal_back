<?php

use App\Http\Controllers\Modulos\ModulosController;
use Illuminate\Support\Facades\Route;

Route::post('/visita', [ModulosController::class, 'registrarVisita']);
Route::get('/mas-visitados', [ModulosController::class, 'masVisitados']);
