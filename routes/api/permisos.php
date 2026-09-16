<?php

use App\Http\Controllers\Permisos\PermisosController;
use Illuminate\Support\Facades\Route;

Route::get('/listado', [PermisosController::class, 'verPermisosPorPerfil']);
Route::get('/opciones', [PermisosController::class, 'verTodosLosPermisosOpciones']);
Route::post('/opciones-perfil', [PermisosController::class, 'verOpcionesPorPerfil']);
Route::post('/activar-permiso', [PermisosController::class, 'crearPermiso']);
Route::delete('/eliminar-permiso', [PermisosController::class, 'eliminarPermiso']);

// CRUD de módulos/opciones — solo Super Admin (ver PermisosController::soloSuperAdmin),
// distinto del resto de este archivo que solo exige la opción 28.
Route::get('/modulos', [PermisosController::class, 'listarModulos']);
Route::post('/modulos', [PermisosController::class, 'crearModulo']);
Route::put('/modulos/{id}', [PermisosController::class, 'actualizarModulo']);
Route::delete('/modulos/{id}', [PermisosController::class, 'eliminarModulo']);

Route::post('/opciones', [PermisosController::class, 'crearOpcion']);
Route::put('/opciones/{id}', [PermisosController::class, 'actualizarOpcion']);
Route::delete('/opciones/{id}', [PermisosController::class, 'eliminarOpcion']);
