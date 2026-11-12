<?php

use App\Http\Controllers\CursosAltura\CursosAlturaController;
use App\Support\Permiso;
use Illuminate\Support\Facades\Route;

// Quienes reportan/programan mantenimientos de Áreas Comunes, y Gestión Humana (143) desde
// Trabajos en altura.
$opcionesAreasYGestion = Permiso::ruta([
    Permiso::CURSOS_ALTURA,
    Permiso::INVENTARIO,
    Permiso::AREAS_COMUNES_ADMIN,
    Permiso::AREAS_COMUNES_USO,
    Permiso::MIS_AREAS_COMUNES,
    Permiso::MIS_AREAS_NIVEL,
    Permiso::MIS_AREAS_RESPONSABLE,
]);

Route::get('/operativos-vigentes', [CursosAlturaController::class, 'operativosVigentes'])->middleware($opcionesAreasYGestion);
Route::get('/permiso/{idReporte}/pdf', [CursosAlturaController::class, 'permisoPdf'])->whereNumber('idReporte')->middleware($opcionesAreasYGestion);

// Trabajos en altura (Gestión Humana)
Route::get('/trabajos', [CursosAlturaController::class, 'listarTrabajos'])->middleware(Permiso::ruta([Permiso::CURSOS_ALTURA]));
Route::get('/trabajos/ubicaciones', [CursosAlturaController::class, 'ubicaciones'])->middleware(Permiso::ruta([Permiso::CURSOS_ALTURA]));
Route::post('/trabajos', [CursosAlturaController::class, 'crearTrabajo'])->middleware(Permiso::ruta([Permiso::CURSOS_ALTURA]));
Route::put('/trabajos/{id}', [CursosAlturaController::class, 'actualizarTrabajo'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::CURSOS_ALTURA]));
Route::put('/trabajos/{id}/estado', [CursosAlturaController::class, 'cambiarEstadoTrabajo'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::CURSOS_ALTURA]));
Route::delete('/trabajos/{id}', [CursosAlturaController::class, 'eliminarTrabajo'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::CURSOS_ALTURA]));
Route::get('/trabajos/{id}/permiso/pdf', [CursosAlturaController::class, 'permisoTrabajoPdf'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::CURSOS_ALTURA]));

// CRUD (Gestión Humana)
Route::get('/operativos', [CursosAlturaController::class, 'operativos'])->middleware(Permiso::ruta([Permiso::CURSOS_ALTURA]));
Route::get('/', [CursosAlturaController::class, 'listar'])->middleware(Permiso::ruta([Permiso::CURSOS_ALTURA]));
Route::post('/', [CursosAlturaController::class, 'crear'])->middleware(Permiso::ruta([Permiso::CURSOS_ALTURA]));
Route::post('/{id}', [CursosAlturaController::class, 'actualizar'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::CURSOS_ALTURA]));
Route::delete('/{id}', [CursosAlturaController::class, 'eliminar'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::CURSOS_ALTURA]));

// Seguridad social (EPS/AFP/ARL) de trabajadores con curso de altura
Route::get('/entidades', [CursosAlturaController::class, 'entidades'])->middleware(Permiso::ruta([Permiso::SEGURIDAD_SOCIAL]));
Route::post('/entidades', [CursosAlturaController::class, 'crearEntidad'])->middleware(Permiso::ruta([Permiso::SEGURIDAD_SOCIAL]));
Route::put('/entidades/{id}', [CursosAlturaController::class, 'actualizarEntidad'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::SEGURIDAD_SOCIAL]));
Route::delete('/entidades/{id}', [CursosAlturaController::class, 'eliminarEntidad'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::SEGURIDAD_SOCIAL]));
Route::get('/seguridad-social/{idUser}', [CursosAlturaController::class, 'seguridadSocial'])->whereNumber('idUser')->middleware(Permiso::ruta([Permiso::SEGURIDAD_SOCIAL]));
Route::post('/seguridad-social/{idUser}', [CursosAlturaController::class, 'guardarSeguridadSocial'])->whereNumber('idUser')->middleware(Permiso::ruta([Permiso::SEGURIDAD_SOCIAL]));
