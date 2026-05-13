<?php

use App\Http\Controllers\Categorias\CategoriasController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CategoriasController::class, 'obtenerTodasLasCategorias']);
Route::post('/', [CategoriasController::class, 'agregarNuevaCategoria']);
Route::put('/', [CategoriasController::class, 'actualizarCategoria']);
