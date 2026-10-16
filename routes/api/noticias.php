<?php

use App\Http\Controllers\Noticias\CumpleanosController;
use App\Http\Controllers\Noticias\NoticiasController;
use Illuminate\Support\Facades\Route;

// Antes de "/": si no, "general"/"para-mostrar" matchean el listado paginado en vez de
// sus propias acciones. Sin permiso de gestión — cualquier usuario del sistema general
// puede verlas (alimenta el contenedor de noticias del Home).
Route::get('/para-mostrar', [NoticiasController::class, 'paraMostrar']);

Route::get('/configuracion', [NoticiasController::class, 'configuracion']);
Route::put('/configuracion', [NoticiasController::class, 'actualizarConfiguracion']);

Route::get('/general', [NoticiasController::class, 'obtenerGeneral']);
Route::put('/general', [NoticiasController::class, 'actualizarGeneral']);

Route::get('/correos-distribucion', [NoticiasController::class, 'correosDistribucion']);
Route::post('/correos-distribucion', [NoticiasController::class, 'crearCorreoDistribucion']);
Route::put('/correos-distribucion/{id}', [NoticiasController::class, 'actualizarCorreoDistribucion']);
Route::delete('/correos-distribucion/{id}', [NoticiasController::class, 'eliminarCorreoDistribucion']);
Route::put('/correos-distribucion/niveles/{idNivel}', [NoticiasController::class, 'asignarGrupoNivel']);

Route::get('/revistas', [NoticiasController::class, 'listarRevistas']);
Route::post('/revistas', [NoticiasController::class, 'crearRevista']);
Route::put('/revistas/{id}', [NoticiasController::class, 'actualizarRevista']);
Route::put('/revistas/{id}/estado', [NoticiasController::class, 'cambiarEstadoRevista']);
Route::delete('/revistas/{id}', [NoticiasController::class, 'eliminarRevista']);

// Configuración de cumpleaños (plantillas por género, datos del personal y automatización)
// — ver CumpleanosService. Mismo permiso que la gestión de Noticias (opción 69).
Route::prefix('cumpleanos')->controller(CumpleanosController::class)->group(function () {
    Route::get('/plantillas', 'listarPlantillas');
    Route::post('/plantillas', 'crearPlantilla');
    Route::get('/plantillas/{id}', 'obtenerPlantilla')->whereNumber('id');
    Route::put('/plantillas/{id}', 'actualizarPlantilla')->whereNumber('id');
    Route::put('/plantillas/{id}/estado', 'cambiarEstadoPlantilla')->whereNumber('id');
    Route::post('/plantillas/{id}/duplicar', 'duplicarPlantilla')->whereNumber('id');
    Route::delete('/plantillas/{id}', 'eliminarPlantilla')->whereNumber('id');
    Route::post('/imagen', 'subirImagen');
    Route::post('/vista-previa', 'vistaPrevia');
    Route::get('/fuentes', 'fuentes');
    Route::get('/fuentes/{archivo}', 'archivoFuente');
    Route::get('/personal', 'personal');
    Route::post('/personal/participacion', 'cambiarParticipacion');
    Route::put('/personal/{idUser}', 'actualizarPersona')->whereNumber('idUser');
    Route::post('/personal/{idUser}/foto', 'subirFotoPersona')->whereNumber('idUser');
    Route::get('/configuracion', 'configuracion');
    Route::put('/configuracion', 'actualizarConfiguracion');
    Route::get('/proximos', 'proximos');
    Route::post('/generar', 'generar');
    Route::post('/regenerar/{idUser}', 'regenerar')->whereNumber('idUser');
});

Route::get('/', [NoticiasController::class, 'listar']);
Route::post('/', [NoticiasController::class, 'crear']);
Route::put('/', [NoticiasController::class, 'actualizar']);
Route::post('/estado', [NoticiasController::class, 'cambiarEstado']);
Route::post('/imagen', [NoticiasController::class, 'subirImagen']);
Route::get('/imagen/{ruta}', [NoticiasController::class, 'verImagen'])->where('ruta', '.*');
