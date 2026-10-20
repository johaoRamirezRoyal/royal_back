<?php

use App\Http\Controllers\Categorias\CategoriasController;
use Illuminate\Support\Facades\Route;
use App\Support\Permiso;

Route::get('/', [CategoriasController::class, 'obtenerTodasLasCategorias'])->middleware(Permiso::ruta([Permiso::INVENTARIO, Permiso::MIS_INVENTARIOS, Permiso::PRESTAMOS_INVENTARIO]));
Route::post('/', [CategoriasController::class, 'agregarNuevaCategoria'])->middleware(Permiso::ruta([Permiso::INVENTARIO]));
Route::put('/', [CategoriasController::class, 'actualizarCategoria'])->middleware(Permiso::ruta([Permiso::INVENTARIO]));
