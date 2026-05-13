<?php

use App\Http\Controllers\Cursos\CursosController;
use Illuminate\Support\Facades\Route;

Route::get('/all', [CursosController::class, 'findAll']);
