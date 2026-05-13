<?php

use App\Http\Controllers\Admissions\AdmissionsController;
use App\Http\Controllers\Areas\AreasController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Categorias\CategoriasController;
use App\Http\Controllers\Cursos\CursosController;
use App\Http\Controllers\Hikvision\HikvisionController;
use App\Http\Controllers\Inventarios\InventariosController;
use App\Http\Controllers\PasswordReset\PasswordResetController;
use App\Http\Controllers\Permisos\PermisosController;
use App\Http\Controllers\Usuarios\UsuariosController;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Mime\DependencyInjection\AddMimeTypeGuesserPass;

Route::get('/', function () {
    return response()->json([
        'message' => 'Bienvenido a la API de Royal School',
        'version' => '1.0',
        'status' => 'success',
    ]);
});

// 🔓 RUTAS PÚBLICAS (sin tok   en)
Route::group(['prefix' => 'auth'], function () {
    require __DIR__ . '/api/auth.php';
});

Route::group(['prefix' => 'admissions'], function () {
    require __DIR__ . '/api/admissions.php';
});

// RUTAS PROTEGIDAS
Route::middleware('auth:api')->group(function () {

    // AUTH
    Route::group(['prefix' => 'auth'], function () {
        require __DIR__ . '/api/auth-protected.php';
    });

    // USUARIOS
    Route::group(['prefix' => 'usuarios'], function () {
        require __DIR__ . '/api/usuarios.php';
    });


    // CURSOS
    Route::group(['prefix' => 'cursos'], function () {
        require __DIR__ . '/api/cursos.php';
    });

    // PERMISOS
    Route::prefix('permisos')->group(function () {
        require __DIR__ . '/api/permisos.php';
    });

    // AREAS
    Route::prefix('areas')->group(function () {
        require __DIR__ . '/api/areas.php';
    });

    // INVENTARIO
    Route::prefix('inventario')->group(function () {
        require __DIR__ . '/api/inventario.php';
    });

    // CATEGORIAS
    Route::prefix('categorias')->group(function () {
        require __DIR__ . '/api/categorias.php';
    });

    //HIKVISION
    Route::prefix('/hikvision')->group(function () {
        require __DIR__ . '/api/hikvision.php';
    });

    // ADMISIONES 
    Route::prefix('/admisiones')->group(function () {
        require __DIR__ . '/api/admisiones.php';
    });
});
