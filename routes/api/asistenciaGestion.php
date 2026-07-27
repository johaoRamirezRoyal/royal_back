<?php

use App\Http\Controllers\AsistenciaGestion\AsistenciaGestionController;
use Illuminate\Support\Facades\Route;

Route::post('/', [AsistenciaGestionController::class, 'registrarAsistencia']);
Route::get('/', [AsistenciaGestionController::class, 'obtenerAsistencia']);
Route::get('/resumen', [AsistenciaGestionController::class, 'obtenerResumenPorUsuario']);
Route::get('/grafica', [AsistenciaGestionController::class, 'obtenerDatosGrafica']);
Route::get('/grafica/top-tardanzas', [AsistenciaGestionController::class, 'topUsuariosLlegadasTarde']);
Route::get('/grafica/distribucion-horas', [AsistenciaGestionController::class, 'distribucionHorasLlegada']);
Route::get('/grafica/promedio-por-usuario', [AsistenciaGestionController::class, 'promedioHoraLlegadaPorUsuario']);
Route::get('/ultimos-registros', [AsistenciaGestionController::class, 'ultimosRegistrosUsuario']);
Route::delete('/', [AsistenciaGestionController::class, 'eliminarAsistencia']);
