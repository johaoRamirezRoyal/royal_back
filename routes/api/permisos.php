<?php

use App\Http\Controllers\Permisos\PermisosController;
use Illuminate\Support\Facades\Route;

Route::get('/listado', [PermisosController::class, 'verPermisosPorPerfil']);
Route::get('/opciones', [PermisosController::class, 'verTodosLosPermisosOpciones']);
Route::post('/opciones-perfil', [PermisosController::class, 'verOpcionesPorPerfil']);
Route::post('/activar-permiso', [PermisosController::class, 'crearPermiso']);
Route::delete('/eliminar-permiso', [PermisosController::class, 'eliminarPermiso']);
