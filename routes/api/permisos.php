<?php

use App\Http\Controllers\Permisos\PermisosController;
use Illuminate\Support\Facades\Route;
use App\Support\Permiso;

Route::get('/listado', [PermisosController::class, 'verPermisosPorPerfil'])->middleware(Permiso::ruta([Permiso::PERMISOS_SISTEMA]));
Route::get('/opciones', [PermisosController::class, 'verTodosLosPermisosOpciones'])->middleware(Permiso::ruta([Permiso::PERMISOS_SISTEMA]));
Route::post('/opciones-perfil', [PermisosController::class, 'verOpcionesPorPerfil'])->middleware(Permiso::ruta([Permiso::PERMISOS_SISTEMA]));
Route::post('/activar-permiso', [PermisosController::class, 'crearPermiso'])->middleware(Permiso::ruta([Permiso::PERMISOS_SISTEMA]));
Route::delete('/eliminar-permiso', [PermisosController::class, 'eliminarPermiso'])->middleware(Permiso::ruta([Permiso::PERMISOS_SISTEMA]));

// CRUD de módulos/opciones — solo Super Admin (ver PermisosController::soloSuperAdmin),
// distinto del resto de este archivo que solo exige la opción 28.
Route::get('/modulos', [PermisosController::class, 'listarModulos'])->middleware(Permiso::ruta([], [Permiso::PERFIL_SUPER_ADMIN]));
Route::post('/modulos', [PermisosController::class, 'crearModulo'])->middleware(Permiso::ruta([], [Permiso::PERFIL_SUPER_ADMIN]));
Route::put('/modulos/{id}', [PermisosController::class, 'actualizarModulo'])->middleware(Permiso::ruta([], [Permiso::PERFIL_SUPER_ADMIN]));
Route::delete('/modulos/{id}', [PermisosController::class, 'eliminarModulo'])->middleware(Permiso::ruta([], [Permiso::PERFIL_SUPER_ADMIN]));

Route::post('/opciones', [PermisosController::class, 'crearOpcion'])->middleware(Permiso::ruta([], [Permiso::PERFIL_SUPER_ADMIN]));
Route::put('/opciones/{id}', [PermisosController::class, 'actualizarOpcion'])->middleware(Permiso::ruta([], [Permiso::PERFIL_SUPER_ADMIN]));
Route::delete('/opciones/{id}', [PermisosController::class, 'eliminarOpcion'])->middleware(Permiso::ruta([], [Permiso::PERFIL_SUPER_ADMIN]));
