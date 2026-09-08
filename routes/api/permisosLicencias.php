<?php

use App\Http\Controllers\PermisosLicencias\PermisosLicenciasController;
use Illuminate\Support\Facades\Route;

// Catálogos (sin gate, necesarios en el formulario de solicitud)
Route::get('/tipos', [PermisosLicenciasController::class, 'tipos']);
Route::get('/motivos', [PermisosLicenciasController::class, 'motivos']);
Route::get('/motivos/ley', [PermisosLicenciasController::class, 'catalogoLey']);
Route::get('/motivos/personal', [PermisosLicenciasController::class, 'catalogoPersonal']);
Route::get('/motivos/institucional', [PermisosLicenciasController::class, 'catalogoInstitucional']);
Route::get('/usuarios-asignables', [PermisosLicenciasController::class, 'usuariosAsignables']);

// Autoservicio (opción 80)
Route::get('/mis-solicitudes', [PermisosLicenciasController::class, 'misSolicitudes']);
Route::post('/', [PermisosLicenciasController::class, 'crear']);
Route::match(['put', 'post'], '/{id}', [PermisosLicenciasController::class, 'actualizar'])->whereNumber('id');

// Gestión (opciones 81/83/90)
Route::get('/', [PermisosLicenciasController::class, 'listar']);
Route::get('/{id}', [PermisosLicenciasController::class, 'detalle'])->whereNumber('id');
Route::post('/{id}/aprobar', [PermisosLicenciasController::class, 'aprobar'])->whereNumber('id');
Route::post('/{id}/rechazar', [PermisosLicenciasController::class, 'rechazar'])->whereNumber('id');
