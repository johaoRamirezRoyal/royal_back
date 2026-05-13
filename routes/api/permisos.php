<?php

use App\Http\Controllers\Permisos\PermisosController;
use Illuminate\Support\Facades\Route;

Route::get('/listado', [PermisosController::class, 'verPermisosPorPerfil']);
Route::get('/opciones', [PermisosController::class, 'verTodosLosPermisosOpciones']);
