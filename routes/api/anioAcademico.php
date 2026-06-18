<?php

use App\Http\Controllers\AnioAcademico\AnioAcademico;
use Illuminate\Support\Facades\Route;

Route::get('/todos', [AnioAcademico::class, 'obtenerAniosAcademicos']);
Route::get('/ultimo', [AnioAcademico::class, 'obtenerUltimoAnioAcademico']);