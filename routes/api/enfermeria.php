<?php

use App\Http\Controllers\Enfermeria\EnfermeriaController;
use Illuminate\Support\Facades\Route;

// CATEGORÍAS
Route::get('/categorias', [EnfermeriaController::class, 'obtenerCategorias']);
Route::get('/categorias/activas', [EnfermeriaController::class, 'obtenerCategoriasActivas']);
Route::get('/categorias/select', [EnfermeriaController::class, 'selectCategorias']);
Route::get('/categorias/conteo', [EnfermeriaController::class, 'obtenerCategoriasConConteo']);
Route::get('/categorias/estadisticas', [EnfermeriaController::class, 'estadisticas']);
Route::get('/categorias/verificar-nombre', [EnfermeriaController::class, 'verificarNombre']);
Route::get('/categorias/{id}', [EnfermeriaController::class, 'obtenerCategoriaPorId']);

Route::post('/categorias', [EnfermeriaController::class, 'crearCategoria']);

Route::put('/categorias/estado', [EnfermeriaController::class, 'cambiarEstadoCategoria']);
Route::put('/categorias/estado-masivo', [EnfermeriaController::class, 'cambiarEstadoMasivo']);
Route::put('/categorias/{id}', [EnfermeriaController::class, 'actualizarCategoria']);

Route::delete('/categorias/{id}', [EnfermeriaController::class, 'eliminarCategoria']);

// ESTADÍSTICAS ENFERMERÍA
Route::get('/estadisticas/cursos', [EnfermeriaController::class, 'cursosConMasAtenciones']);
Route::get('/estadisticas/estudiantes', [EnfermeriaController::class, 'estudiantesConMasAtenciones']);
Route::get('/estadisticas/categorias', [EnfermeriaController::class, 'categoriasMasRegistradas']);

// ATENCIONES
Route::get('/atenciones', [EnfermeriaController::class, 'obtenerAtenciones']);
Route::get('/atenciones/estadisticas', [EnfermeriaController::class, 'estadisticasAtenciones']);
Route::get('/atenciones/{id}', [EnfermeriaController::class, 'obtenerAtencionPorId']);

Route::post('/atenciones', [EnfermeriaController::class, 'crearAtencion']);
Route::post('/atenciones/{id}/reenviar-correo', [EnfermeriaController::class, 'reenviarCorreo']);

Route::delete('/atenciones/{id}', [EnfermeriaController::class, 'eliminarAtencion']);
