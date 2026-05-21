<?php

use App\Http\Controllers\Biblioteca\BibliotecaController;
use Illuminate\Support\Facades\Route;

Route::get("/categorias", [BibliotecaController::class, "mostrarCategoriasBiblioteca"]);
Route::post("/categoria", [BibliotecaController::class, "agregarCategoriaBiblioteca"]);
Route::put("/categoria/estado", [BibliotecaController::class, "cambiarEstadoCategoriaBiblioteca"]);
Route::get("/subcategorias", [BibliotecaController::class, "mostrarSubcategoriasBiblioteca"]);
Route::post("/subcategorias", [BibliotecaController::class, "agregarSubcategoriaBiblioteca"]);
Route::put("/subcategorias/estado", [BibliotecaController::class, "cambiarEstadoSubcategoriaBiblioteca"]);