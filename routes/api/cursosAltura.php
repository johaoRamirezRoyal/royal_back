<?php

use App\Http\Controllers\CursosAltura\CursosAlturaController;
use App\Support\Permiso;
use Illuminate\Support\Facades\Route;

// Lo consumen quienes reportan/programan mantenimientos de Áreas Comunes.
$opcionesAreas = Permiso::ruta([
    Permiso::INVENTARIO,
    Permiso::AREAS_COMUNES_ADMIN,
    Permiso::AREAS_COMUNES_USO,
    Permiso::MIS_AREAS_COMUNES,
    Permiso::MIS_AREAS_NIVEL,
    Permiso::MIS_AREAS_RESPONSABLE,
]);

Route::get('/operativos-vigentes', [CursosAlturaController::class, 'operativosVigentes'])->middleware($opcionesAreas);
Route::get('/permiso/{idReporte}/pdf', [CursosAlturaController::class, 'permisoPdf'])->whereNumber('idReporte')->middleware($opcionesAreas);

// CRUD (Gestión Humana)
Route::get('/operativos', [CursosAlturaController::class, 'operativos'])->middleware(Permiso::ruta([Permiso::CURSOS_ALTURA]));
Route::get('/', [CursosAlturaController::class, 'listar'])->middleware(Permiso::ruta([Permiso::CURSOS_ALTURA]));
Route::post('/', [CursosAlturaController::class, 'crear'])->middleware(Permiso::ruta([Permiso::CURSOS_ALTURA]));
Route::post('/{id}', [CursosAlturaController::class, 'actualizar'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::CURSOS_ALTURA]));
Route::delete('/{id}', [CursosAlturaController::class, 'eliminar'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::CURSOS_ALTURA]));
