<?php

use App\Http\Controllers\Reservas\SalonesController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SalonesController::class, 'listarHoras']);
