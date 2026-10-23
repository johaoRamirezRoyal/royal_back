<?php

use App\Http\Controllers\AsistenciaGestion\AsistenciaGestionController;
use App\Http\Controllers\AsistenciaGestion\AsistenciaHorariosController;
use Illuminate\Support\Facades\Route;
use App\Support\Permiso;

// Registro manual de una asistencia (RH corrige un olvido del dispositivo) — ver
// AsistenciaGestionController::registrarAsistenciaManual, exige la opción de "ver todas".
Route::post('/manual', [AsistenciaGestionController::class, 'registrarAsistenciaManual']);
// Marcación con QR (respaldo de las terminales): la pantalla de portería pide el token que
// cambia cada 30 s; el trabajador lo escanea y marca con su propia sesión.
Route::get('/qr/token', [AsistenciaGestionController::class, 'tokenQr'])->middleware(Permiso::ruta([Permiso::ASISTENCIA_TRABAJADORES]));
Route::post('/qr/marcar', [AsistenciaGestionController::class, 'marcarConQr'])->middleware('throttle:asistencia');
Route::get('/', [AsistenciaGestionController::class, 'obtenerAsistencia']);
Route::get('/grafica', [AsistenciaGestionController::class, 'obtenerDatosGrafica']);
Route::get('/grafica/top-tardanzas', [AsistenciaGestionController::class, 'topUsuariosLlegadasTarde']);
Route::get('/grafica/distribucion-horas', [AsistenciaGestionController::class, 'distribucionHorasLlegada']);
Route::delete('/', [AsistenciaGestionController::class, 'eliminarAsistencia']);

// CONFIGURACIÓN — horarios estándar y bandas de puntualidad (solo RH/Administradores, ver
// AsistenciaHorariosController). Antes de /{id}: si no, "configuracion" matchea el wildcard
// {id} y actualizarObservacion() se lleva la petición en vez de actualizarConfiguracion().
Route::get('/configuracion', [AsistenciaHorariosController::class, 'obtenerConfiguracion'])->middleware(Permiso::ruta([Permiso::CONFIGURACION_ASISTENCIA]));
Route::put('/configuracion', [AsistenciaHorariosController::class, 'actualizarConfiguracion'])->middleware(Permiso::ruta([Permiso::CONFIGURACION_ASISTENCIA]));

Route::put('/{id}/revocar', [AsistenciaGestionController::class, 'revocarLlegadaTarde']);

Route::put('/{id}', [AsistenciaGestionController::class, 'actualizarObservacion']);

Route::prefix('/horarios')->group(function () {
    Route::get('/', [AsistenciaHorariosController::class, 'index'])->middleware(Permiso::ruta([Permiso::CONFIGURACION_ASISTENCIA]));
    Route::post('/', [AsistenciaHorariosController::class, 'store'])->middleware(Permiso::ruta([Permiso::CONFIGURACION_ASISTENCIA]));
    Route::put('/{id}', [AsistenciaHorariosController::class, 'update'])->middleware(Permiso::ruta([Permiso::CONFIGURACION_ASISTENCIA]));
    Route::delete('/{id}', [AsistenciaHorariosController::class, 'destroy'])->middleware(Permiso::ruta([Permiso::CONFIGURACION_ASISTENCIA]));
    Route::post('/{id}/bandas', [AsistenciaHorariosController::class, 'storeBanda'])->middleware(Permiso::ruta([Permiso::CONFIGURACION_ASISTENCIA]));
});
// Antes de /bandas/{id}: si no, "orden" matchea el wildcard {id} y nunca llega acá.
Route::put('/bandas/orden', [AsistenciaHorariosController::class, 'reordenarBandas'])->middleware(Permiso::ruta([Permiso::CONFIGURACION_ASISTENCIA]));
Route::put('/bandas/{id}', [AsistenciaHorariosController::class, 'updateBanda'])->middleware(Permiso::ruta([Permiso::CONFIGURACION_ASISTENCIA]));
Route::delete('/bandas/{id}', [AsistenciaHorariosController::class, 'destroyBanda'])->middleware(Permiso::ruta([Permiso::CONFIGURACION_ASISTENCIA]));
