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

    // Lista los usuarios con perfil de psicóloga (preescolar/primaria/bachillerato) y estado activo.
    Route::get('/psicologasDisponibles', [AdmissionsController::class, 'listarPsicologasDisponibles']);

    /**
     * Ejemplo JSON para agendar una cita de psicología (la define la psicóloga):
        {
            "id_inscripcion": 1,
            "id_psicologa": 45,
            "fecha_cita": "2026-07-20 09:00:00",
            "observaciones": "Primera valoración con la familia."
        }
     * Notifica (in-app) al acudiente que registró la inscripción.
     */
    Route::post('/citaPsicologia', [AdmissionsController::class, 'agendarCitaPsicologia']);

    // ?id_inscripcion=1 -> { data: { tiene_cita: bool, citas: [...] } }
    Route::get('/citaPsicologia', [AdmissionsController::class, 'obtenerCitasPsicologiaDeInscripcion']);

    // Reprogramar una cita: { "id": 7, "fecha_cita": "2026-07-21 10:00:00" }
    Route::put('/citaPsicologia', [AdmissionsController::class, 'actualizarFechaCitaPsicologia']);

    // Reasignar la psicóloga a cargo: { "id": 7, "id_psicologa": 46 }. Envía correo al acudiente y a la nueva psicóloga.
    Route::put('/citaPsicologia/psicologa', [AdmissionsController::class, 'actualizarPsicologaCitaPsicologia']);

    // ?id_psicologa=45&fecha_desde=2026-07-01&fecha_hasta=2026-07-31 (todos opcionales; sin filtros trae todas)
    Route::get('/citasPsicologia', [AdmissionsController::class, 'listarCitasPsicologia']);
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

    // PRÉSTAMOS
    Route::prefix('prestamos')->group(function () {
        require __DIR__.'/api/prestamos.php';
    });

    // RESERVAS
    Route::prefix('reservas')->group(function () {
        require __DIR__.'/api/reservas.php';
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

    // GESTIÓN ACADÉMICA
    Route::prefix('/gestion-academica')->group(function () {
        require __DIR__ . '/api/gestionAcademica.php';
    });

});
