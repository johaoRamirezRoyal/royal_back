<?php

use App\Http\Controllers\AsistenciaGestion\AsistenciaGestionController;
use Illuminate\Support\Facades\Route;

Route::post('/', [AsistenciaGestionController::class, 'registrarAsistencia']);
Route::get('/', [AsistenciaGestionController::class, 'obtenerAsistencia']);
Route::get('/resumen', [AsistenciaGestionController::class, 'obtenerResumenPorUsuario']);
Route::get('/grafica', [AsistenciaGestionController::class, 'obtenerDatosGrafica']);
Route::delete('/', [AsistenciaGestionController::class, 'eliminarAsistencia']);
