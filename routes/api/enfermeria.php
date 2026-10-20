<?php

use App\Http\Controllers\Enfermeria\EnfermeriaController;
use Illuminate\Support\Facades\Route;
use App\Support\Permiso;

// CATEGORÍAS
Route::get('/categorias', [EnfermeriaController::class, 'obtenerCategorias'])->middleware(Permiso::ruta([Permiso::ENFERMERIA_CATEGORIAS]));
Route::get('/categorias/activas', [EnfermeriaController::class, 'obtenerCategoriasActivas'])->middleware(Permiso::ruta([Permiso::ENFERMERIA_CATEGORIAS]));
Route::get('/categorias/select', [EnfermeriaController::class, 'selectCategorias'])->middleware(Permiso::ruta([Permiso::ENFERMERIA_CATEGORIAS, Permiso::ENFERMERIA_ATENCIONES]));
Route::get('/categorias/conteo', [EnfermeriaController::class, 'obtenerCategoriasConConteo'])->middleware(Permiso::ruta([Permiso::ENFERMERIA_CATEGORIAS]));
Route::get('/categorias/estadisticas', [EnfermeriaController::class, 'estadisticas'])->middleware(Permiso::ruta([Permiso::ENFERMERIA_CATEGORIAS]));
Route::get('/categorias/verificar-nombre', [EnfermeriaController::class, 'verificarNombre'])->middleware(Permiso::ruta([Permiso::ENFERMERIA_CATEGORIAS]));
Route::get('/categorias/{id}', [EnfermeriaController::class, 'obtenerCategoriaPorId'])->middleware(Permiso::ruta([Permiso::ENFERMERIA_CATEGORIAS]));

Route::post('/categorias', [EnfermeriaController::class, 'crearCategoria'])->middleware(Permiso::ruta([Permiso::ENFERMERIA_CATEGORIAS]));

Route::put('/categorias/estado', [EnfermeriaController::class, 'cambiarEstadoCategoria'])->middleware(Permiso::ruta([Permiso::ENFERMERIA_CATEGORIAS]));
Route::put('/categorias/estado-masivo', [EnfermeriaController::class, 'cambiarEstadoMasivo'])->middleware(Permiso::ruta([Permiso::ENFERMERIA_CATEGORIAS]));
Route::put('/categorias/{id}', [EnfermeriaController::class, 'actualizarCategoria'])->middleware(Permiso::ruta([Permiso::ENFERMERIA_CATEGORIAS]));

Route::delete('/categorias/{id}', [EnfermeriaController::class, 'eliminarCategoria'])->middleware(Permiso::ruta([Permiso::ENFERMERIA_CATEGORIAS]));

// ESTADÍSTICAS ENFERMERÍA
Route::get('/estadisticas/cursos', [EnfermeriaController::class, 'cursosConMasAtenciones'])->middleware(Permiso::ruta([Permiso::ENFERMERIA_METRICAS]));
Route::get('/estadisticas/estudiantes', [EnfermeriaController::class, 'estudiantesConMasAtenciones'])->middleware(Permiso::ruta([Permiso::ENFERMERIA_METRICAS]));
Route::get('/estadisticas/categorias', [EnfermeriaController::class, 'categoriasMasRegistradas'])->middleware(Permiso::ruta([Permiso::ENFERMERIA_METRICAS]));

// ATENCIONES
Route::get('/atenciones', [EnfermeriaController::class, 'obtenerAtenciones'])->middleware(Permiso::ruta([Permiso::ENFERMERIA_ATENCIONES]));
Route::get('/atenciones/estadisticas', [EnfermeriaController::class, 'estadisticasAtenciones'])->middleware(Permiso::ruta([Permiso::ENFERMERIA_ATENCIONES]));
Route::get('/atenciones/{id}', [EnfermeriaController::class, 'obtenerAtencionPorId'])->middleware(Permiso::ruta([Permiso::ENFERMERIA_ATENCIONES]));

Route::post('/atenciones', [EnfermeriaController::class, 'crearAtencion'])->middleware(Permiso::ruta([Permiso::ENFERMERIA_ATENCIONES]));
Route::post('/atenciones/{id}/reenviar-correo', [EnfermeriaController::class, 'reenviarCorreo'])->middleware(Permiso::ruta([Permiso::ENFERMERIA_ATENCIONES]));

Route::delete('/atenciones/{id}', [EnfermeriaController::class, 'eliminarAtencion'])->middleware(Permiso::ruta([Permiso::ENFERMERIA_ATENCIONES]));
