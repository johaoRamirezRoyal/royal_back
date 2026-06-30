<?php

use App\Http\Controllers\Admissions\AdmissionsController;
use App\Http\Controllers\Hikvision\HikvisionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Bienvenido a la API de Royal School',
        'version' => '1.0',
        'status' => 'success',
    ]);
});

Route::post('/pushNotification', [HikvisionController::class, 'testNotificationHikvision']);

// RUTAS PÚBLICAS (sin token)
Route::group(['prefix' => 'auth'], function () {
    require __DIR__.'/api/auth.php';
});

// Imágenes públicas de biblioteca (sin token — se accede desde <img src>)
Route::get('/biblioteca/imagen/{carpeta}/{filename}', [App\Http\Controllers\Biblioteca\BibliotecaController::class, 'verImagenBiblioteca'])
    ->where('filename', '.+');

// ENDPOINTS COMPARTIDOS: accesibles para system:admissions y system:general
Route::middleware(['auth:api'])->prefix('/compartido')->group(function () {
    Route::put('/inscripcion/estado', [AdmissionsController::class, 'actualizarEstadoDeInscripcionAspirante']);
    Route::get('/estadosIncripcion', [AdmissionsController::class, 'mostrarTodosLosEstadosDeInscripcion']);
    Route::group(['prefix' => 'anio-academico'], function () {
        require __DIR__.'/api/anioAcademico.php';
    });
    Route::put('/inscripcion', [AdmissionsController::class, 'actualizarDatosInscripcion']);
    Route::get('/inscripcionesPsicologa', [AdmissionsController::class, 'mostrarAspirantesAPsicologa']);
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

    // TIPOS DE DOCUMENTOS
    Route::prefix('/admisiones/tipos-documentos')->group(function () {
        require __DIR__.'/api/TipoDocumentos.php';
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

    // BIBLIOTECA
    Route::prefix('/biblioteca')->group(function () {
        require __DIR__.'/api/Biblioteca.php';
    });

    // TIPOS DE DOCUMENTOS
    Route::prefix('/tipos-documentos')->group(function () {
        require __DIR__.'/api/TipoDocumentos.php';
    });

    //LLEGADAS TARDE
    Route::prefix("/llegadas-tarde")->group(function () {
        require __DIR__ . '/api/llegadasTarde.php';
    });

});
