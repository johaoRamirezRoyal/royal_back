<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Bienvenido a la API de Royal School',
        'version' => '1.0',
        'status' => 'success',
    ]);
});

// 🔓 RUTAS PÚBLICAS (sin tok   en)
Route::group(['prefix' => 'auth'], function () {
    require __DIR__.'/api/auth.php';
});

Route::group(['prefix' => 'admissions'], function () {
    require __DIR__.'/api/admissions.php';
});

// Ruta protegida de la pagina de admisiones
Route::middleware(['auth:api', 'system:admissions'])->group(function () {
    // ADMISIONES
    Route::prefix('/admisiones')->group(function () {
        require __DIR__.'/api/admisiones.php';
    });
});

// RUTAS PROTEGIDAS (pagina principal | administracion)
Route::middleware(['auth:api', 'system:general'])->group(function () {

    // AUTH
    Route::group(['prefix' => 'auth'], function () {
        require __DIR__.'/api/auth-protected.php';
    });

    // USUARIOS
    Route::group(['prefix' => 'usuarios'], function () {
        require __DIR__.'/api/usuarios.php';
    });

    // CURSOS
    Route::group(['prefix' => 'cursos'], function () {
        require __DIR__.'/api/cursos.php';
    });

    // PERMISOS
    Route::prefix('permisos')->group(function () {
        require __DIR__.'/api/permisos.php';
    });

    // AREAS
    Route::prefix('areas')->group(function () {
        require __DIR__.'/api/areas.php';
    });

    // INVENTARIO
    Route::prefix('inventario')->group(function () {
        require __DIR__.'/api/inventario.php';
    });

    // CATEGORIAS
    Route::prefix('categorias')->group(function () {
        require __DIR__.'/api/categorias.php';
    });

    // HIKVISION
    Route::prefix('/hikvision')->group(function () {
        require __DIR__.'/api/hikvision.php';
    });
});
