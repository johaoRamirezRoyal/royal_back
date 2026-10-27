<?php

use App\Http\Controllers\Categorias\CategoriasController;
use App\Support\Permiso;
use Illuminate\Support\Facades\Route;

Route::get('/', [CategoriasController::class, 'obtenerTodasLasCategorias'])->middleware(Permiso::ruta([Permiso::INVENTARIO, Permiso::MIS_INVENTARIOS, Permiso::PRESTAMOS_INVENTARIO, Permiso::MIS_AREAS_NIVEL, Permiso::MIS_AREAS_RESPONSABLE]));
Route::post('/', [CategoriasController::class, 'agregarNuevaCategoria'])->middleware(Permiso::ruta([Permiso::INVENTARIO]));
Route::put('/', [CategoriasController::class, 'actualizarCategoria'])->middleware(Permiso::ruta([Permiso::INVENTARIO]));
