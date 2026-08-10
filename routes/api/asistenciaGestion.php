<?php

use App\Http\Controllers\AsistenciaGestion\AsistenciaGestionController;
use App\Http\Controllers\AsistenciaGestion\AsistenciaHorariosController;
use Illuminate\Support\Facades\Route;

// throttle: 20 peticiones/min por IP de dispositivo (ver AppServiceProvider::boot) —
// evita que un doble-tap o un retry dispare varias marcaciones seguidas, sin que un
// kiosco compartido por varios trabajadores agote la cuota entre ellos.
Route::post('/', [AsistenciaGestionController::class, 'registrarAsistencia'])->middleware('throttle:asistencia');
Route::get('/', [AsistenciaGestionController::class, 'obtenerAsistencia']);
Route::get('/resumen', [AsistenciaGestionController::class, 'obtenerResumenPorUsuario']);
Route::get('/grafica', [AsistenciaGestionController::class, 'obtenerDatosGrafica']);
Route::get('/grafica/top-tardanzas', [AsistenciaGestionController::class, 'topUsuariosLlegadasTarde']);
Route::get('/grafica/distribucion-horas', [AsistenciaGestionController::class, 'distribucionHorasLlegada']);
Route::get('/grafica/promedio-por-usuario', [AsistenciaGestionController::class, 'promedioHoraLlegadaPorUsuario']);
Route::get('/ultimos-registros', [AsistenciaGestionController::class, 'ultimosRegistrosUsuario']);
Route::delete('/', [AsistenciaGestionController::class, 'eliminarAsistencia']);
Route::put('/{id}', [AsistenciaGestionController::class, 'actualizarObservacion']);

// CONFIGURACIÓN — horarios estándar y bandas de puntualidad (solo RH/Administradores, ver AsistenciaHorariosController)
Route::prefix('/horarios')->group(function () {
    Route::get('/', [AsistenciaHorariosController::class, 'index']);
    Route::post('/', [AsistenciaHorariosController::class, 'store']);
    Route::put('/{id}', [AsistenciaHorariosController::class, 'update']);
    Route::delete('/{id}', [AsistenciaHorariosController::class, 'destroy']);
    Route::post('/{id}/bandas', [AsistenciaHorariosController::class, 'storeBanda']);
});
// Antes de /bandas/{id}: si no, "orden" matchea el wildcard {id} y nunca llega acá.
Route::put('/bandas/orden', [AsistenciaHorariosController::class, 'reordenarBandas']);
Route::put('/bandas/{id}', [AsistenciaHorariosController::class, 'updateBanda']);
Route::delete('/bandas/{id}', [AsistenciaHorariosController::class, 'destroyBanda']);
