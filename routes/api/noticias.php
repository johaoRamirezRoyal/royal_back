<?php

use App\Http\Controllers\Noticias\NoticiasController;
use Illuminate\Support\Facades\Route;

// Antes de "/": si no, "general" matchea el listado paginado en vez del mensaje general.
Route::get('/general', [NoticiasController::class, 'obtenerGeneral']);
Route::put('/general', [NoticiasController::class, 'actualizarGeneral']);

Route::get('/', [NoticiasController::class, 'listar']);
Route::post('/', [NoticiasController::class, 'crear']);
Route::put('/', [NoticiasController::class, 'actualizar']);
Route::post('/estado', [NoticiasController::class, 'cambiarEstado']);
Route::post('/imagen', [NoticiasController::class, 'subirImagen']);
Route::get('/imagen/{ruta}', [NoticiasController::class, 'verImagen'])->where('ruta', '.*');
