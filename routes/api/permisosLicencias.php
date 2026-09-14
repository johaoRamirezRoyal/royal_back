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

// Configuración de catálogos (opción 121) — admin de motivo/ley/personal/institucional,
// separado de los catálogos de solo lectura de arriba (que siguen siendo públicos y solo
// devuelven los activos). {tipo} = motivo|ley|personal|institucional.
Route::get('/catalogos/{tipo}', [PermisosLicenciasController::class, 'listarCatalogo']);
Route::post('/catalogos/{tipo}', [PermisosLicenciasController::class, 'crearCatalogo']);
Route::put('/catalogos/{tipo}/{id}', [PermisosLicenciasController::class, 'actualizarCatalogo'])->whereNumber('id');
Route::put('/catalogos/{tipo}/{id}/estado', [PermisosLicenciasController::class, 'estadoCatalogo'])->whereNumber('id');
