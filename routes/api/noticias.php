<?php

use App\Http\Controllers\Noticias\CumpleanosController;
use App\Http\Controllers\Noticias\NoticiasController;
use Illuminate\Support\Facades\Route;
use App\Support\Permiso;

// Antes de "/": si no, "general"/"para-mostrar" matchean el listado paginado en vez de
// sus propias acciones. Sin permiso de gestión — cualquier usuario del sistema general
// puede verlas (alimenta el contenedor de noticias del Home).
Route::get('/para-mostrar', [NoticiasController::class, 'paraMostrar']);

Route::get('/configuracion', [NoticiasController::class, 'configuracion'])->middleware(Permiso::ruta([Permiso::NOTICIAS], [Permiso::PERFIL_SUPER_ADMIN]));
Route::put('/configuracion', [NoticiasController::class, 'actualizarConfiguracion'])->middleware(Permiso::ruta([Permiso::NOTICIAS], [Permiso::PERFIL_SUPER_ADMIN]));

Route::get('/general', [NoticiasController::class, 'obtenerGeneral'])->middleware(Permiso::ruta([Permiso::NOTICIAS], [Permiso::PERFIL_SUPER_ADMIN]));
Route::put('/general', [NoticiasController::class, 'actualizarGeneral'])->middleware(Permiso::ruta([Permiso::NOTICIAS], [Permiso::PERFIL_SUPER_ADMIN]));

Route::get('/correos-distribucion', [NoticiasController::class, 'correosDistribucion'])->middleware(Permiso::ruta([Permiso::NOTICIAS_CORREOS_DISTRIBUCION], [Permiso::PERFIL_SUPER_ADMIN]));
Route::post('/correos-distribucion', [NoticiasController::class, 'crearCorreoDistribucion'])->middleware(Permiso::ruta([Permiso::NOTICIAS_CORREOS_DISTRIBUCION], [Permiso::PERFIL_SUPER_ADMIN]));
Route::put('/correos-distribucion/{id}', [NoticiasController::class, 'actualizarCorreoDistribucion'])->middleware(Permiso::ruta([Permiso::NOTICIAS_CORREOS_DISTRIBUCION], [Permiso::PERFIL_SUPER_ADMIN]));
Route::delete('/correos-distribucion/{id}', [NoticiasController::class, 'eliminarCorreoDistribucion'])->middleware(Permiso::ruta([Permiso::NOTICIAS_CORREOS_DISTRIBUCION], [Permiso::PERFIL_SUPER_ADMIN]));
Route::put('/correos-distribucion/niveles/{idNivel}', [NoticiasController::class, 'asignarGrupoNivel'])->middleware(Permiso::ruta([Permiso::NOTICIAS_CORREOS_DISTRIBUCION], [Permiso::PERFIL_SUPER_ADMIN]));

Route::get('/revistas', [NoticiasController::class, 'listarRevistas'])->middleware(Permiso::ruta([Permiso::NOTICIAS], [Permiso::PERFIL_SUPER_ADMIN]));
Route::post('/revistas', [NoticiasController::class, 'crearRevista'])->middleware(Permiso::ruta([Permiso::NOTICIAS], [Permiso::PERFIL_SUPER_ADMIN]));
Route::put('/revistas/{id}', [NoticiasController::class, 'actualizarRevista'])->middleware(Permiso::ruta([Permiso::NOTICIAS], [Permiso::PERFIL_SUPER_ADMIN]));
Route::put('/revistas/{id}/estado', [NoticiasController::class, 'cambiarEstadoRevista'])->middleware(Permiso::ruta([Permiso::NOTICIAS], [Permiso::PERFIL_SUPER_ADMIN]));
Route::delete('/revistas/{id}', [NoticiasController::class, 'eliminarRevista'])->middleware(Permiso::ruta([Permiso::NOTICIAS], [Permiso::PERFIL_SUPER_ADMIN]));

// Configuración de cumpleaños (plantillas por género, datos del personal y automatización)
// — ver CumpleanosService. Mismo permiso que la gestión de Noticias (opción 69).
Route::prefix('cumpleanos')->controller(CumpleanosController::class)->middleware(Permiso::ruta([Permiso::NOTICIAS], [Permiso::PERFIL_SUPER_ADMIN]))->group(function () {
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
    Route::post('/correo/{idMensaje}/reenviar', 'reenviarCorreo')->whereNumber('idMensaje');
});

Route::get('/', [NoticiasController::class, 'listar'])->middleware(Permiso::ruta([Permiso::NOTICIAS], [Permiso::PERFIL_SUPER_ADMIN]));
Route::post('/', [NoticiasController::class, 'crear'])->middleware(Permiso::ruta([Permiso::NOTICIAS], [Permiso::PERFIL_SUPER_ADMIN]));
Route::put('/', [NoticiasController::class, 'actualizar'])->middleware(Permiso::ruta([Permiso::NOTICIAS], [Permiso::PERFIL_SUPER_ADMIN]));
Route::post('/estado', [NoticiasController::class, 'cambiarEstado'])->middleware(Permiso::ruta([Permiso::NOTICIAS], [Permiso::PERFIL_SUPER_ADMIN]));
Route::post('/imagen', [NoticiasController::class, 'subirImagen'])->middleware(Permiso::ruta([Permiso::NOTICIAS], [Permiso::PERFIL_SUPER_ADMIN]));
Route::get('/imagen/{ruta}', [NoticiasController::class, 'verImagen'])->where('ruta', '.*');
