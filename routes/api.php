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
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::get('/check', [AuthController::class, 'check']);

    Route::prefix('password')->group(function () {
        Route::post('restore', [PasswordResetController::class, 'createToken']);
        Route::post('validate-token', [PasswordResetController::class, 'validateToken']);
        Route::patch('update-password', [PasswordResetController::class, 'resetPassword']);
    });

});

Route::prefix('admissions')->group(function () {
    Route::post('request-validation', [AdmissionsController::class, 'requestVerification']);
    Route::post('validate-session', [AdmissionsController::class, 'validateVerificationCode']);
    Route::post('validate-code', [AdmissionsController::class, 'forgetVerificationCode']);
    Route::post('register-guardian', [AdmissionsController::class, 'familyRegister']);
});

// RUTAS PROTEGIDAS
Route::middleware('auth:api')->group(function () {

    // AUTH
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
    });

    // USUARIOS
    Route::prefix('usuarios')->group(function () {
        Route::get('/permiso', [UsuariosController::class, 'tienePermiso']);
        Route::get('/filtro', [UsuariosController::class, 'filtrarUsuarios']);
        Route::get('/perfiles', [UsuariosController::class, 'mostrarTodosPerfiles']);
        Route::get('/niveles', [UsuariosController::class, 'mostrarTodosNiveles']);

        Route::get('/all/activos', [UsuariosController::class, 'mostrarTodosUsuariosActivos']);
        Route::get('/all/general', [UsuariosController::class, 'mostrarTodosUsuariosPaginado']);
        Route::get('/', [UsuariosController::class, 'mostrarTodosUsuariosActivoPaginado']);
        Route::get('/all', [UsuariosController::class, 'mostrarTodosUsuarios']);

        Route::get('/{id}', [UsuariosController::class, 'mostrarInfoUsuarioId'])->where('id', '[0-9]+');

        Route::put('/estado', [UsuariosController::class, 'actualizarEstadoUsuarios']);
        /**
         * Ejemplo de JSON para actualizar estado de varios usuarios:
        {
        "ids": [
                11,
                12,
                13
            ],
        "estado": "activo"
        }
         */
        Route::put('/{id}', [UsuariosController::class, 'actualizarUsuarios']);
        Route::post('/', [UsuariosController::class, 'agregarUsuario']);
        /**
         * Ejemplo de JSON para agregar usuario:
         *
         *
        {
            "documento": 10203040,
            "nombre": "Pepito",
            "apellido": "Pérez",
            "correo": "pepito_perez@royalschool.edu.co",
            "perfil": 2,
            "id_nivel": 1,
            "user": "aperez2026",
            "pass": "secret123",
            "grupo": 1,
            "curso": 5
        }
         */
    });

    // CURSOS
    Route::prefix('cursos')->group(function () {
        Route::get('/all', [CursosController::class, 'findAll']);
    });

    // PERMISOS
    Route::prefix('permisos')->group(function () {
        Route::get('/listado', [PermisosController::class, 'verPermisosPorPerfil']);
        Route::get('/opciones', [PermisosController::class, 'verTodosLosPermisosOpciones']);
    });

    // AREAS
    Route::prefix('areas')->group(function () {
        Route::put('/', [AreasController::class, 'actualizarArea']);
        /**
         * Ejemplo de JSON para actualizar un area:
        {
            "id": 1,
            "nombre": "S40 (1B)",
            "user_log": 1,
            "activo": 1
        }
         */
        Route::get('/filtro', [AreasController::class, 'filtrarAreas']);
        /*
            Filtrada area
            http://localhost:8000/api/areas/filtro?filtro=filtro_a_buscar
        */

        Route::post('/asignar', [AreasController::class, 'asignarArea']);
        /**
         * Ejemplo de JSON para asignar un area a un usuario:
            {
                "id_user": 3123,
                "id_area": 104
            }
         */
        Route::post('/estado', [AreasController::class, 'desactivarAreas']);
        /**
         * Ejemplo JSON para cambiar el estado del area:
        {
            "ids": [
                    1, 2, 3, 4, 5
                    ],
            "estado": 1 -> activo | 0 -> inactivo
        }
         */
        Route::get('/', [AreasController::class, 'obtenerTodasLasAreas']);
    });

    // INVENTARIO
    Route::prefix('inventario')->group(function () {
        Route::get('/listado', [InventariosController::class, 'obtenerListadoInventario']);
        /**
         *  Ejemplo de JSON para obtener el listado de inventario paginado:
            {
                "search": "computado",
                "id_categoria": [],
                "estado": [],
                "id_usuario": 3123,
                "per-page": 20
            }

         * URL Puede ser:
            http://localhost:8000/api/inventario/listado?page=300&per_page=10
         */
        Route::put('/descontinuar', [InventariosController::class, 'descontinuarInventario']);
        Route::post('/', [InventariosController::class, 'agregarInventario']);
        Route::put('/liberar', [InventariosController::class, 'liberarInventario']);
        Route::put('/asignar', [InventariosController::class, 'asignarInventario']);
    });

    // CATEGORIAS
    Route::prefix('categorias')->group(function () {
        Route::get('/', [CategoriasController::class, 'obtenerTodasLasCategorias']);
        Route::post('/', [CategoriasController::class, 'agregarNuevaCategoria']);
        Route::put('/', [CategoriasController::class, 'actualizarCategoria']);
    });

    //HIKVISION
    Route::prefix('/hikvision')->group(function () {
        Route::post("/", [HikvisionController::class, 'registrarEmpleadosMasivoPerfil']);
        Route::get('/testHikvision', [HikvisionController::class, 'testHikvisionConexion']);
        Route::get('/getList', [HikvisionController::class, 'obtenerEmpleadosRegistrados']);
        Route::get("/userInfo/perfil", [HikvisionController::class, 'obtenerEmpleadosRegistradosPorPerfil']);
        Route::get("/userInfo", [HikvisionController::class, 'obtenerUnEmpleadoEspecifico']);
        Route::delete("/perfil", [HikvisionController::class, 'eliminarUsuariosRegistrados']);
        Route::put("/desactivar", [HikvisionController::class, 'desactivarUsuario']);
    });

    // ADMISIONES 
    Route::prefix('/admisiones')->group(function () {

        /** Ejemplo JSON para registrar una inscripcion:
         * 
        {
            "estado": "PENDIENTE",
            "id_usuario_registro": 3123,
            "anio_academico": 8,
            "fecha_inscripcion": "2026-08-15"
        }
         */
        Route::post('/inscripcion', [AdmissionsController::class, 'registrarInscripcion']);
        Route::get('/inscripcion', [AdmissionsController::class, 'obtenerInformacionCompletaDeInscripcionMedianteCodigo']);

        /** Ejemplo JSON para actualizar datos de un aspirante:
        {
            "id": 2,
            "lugar_nacimiento": "Bogotá, Colombia",
            "fecha_nacimiento": "2015-05-20",
            "edad": 9,
            "sexo": "Femenino",
            "lengua_materna": "Español",
            "otros_idiomas": "Inglés básico",
            "religion": "Católica",
            "vive_con": "Ambos Padres", //-> Es un ENUM con los valores ["Padre", "Madre", "Ambos Padres", "Acudiente"]
            "num_hermanos": 2,
            "posicion_entre_hermanos": 1,
            "tiene_hermanos_colegio": true,
            "info_hermanos_colegio": "Su hermano mayor está en 5to grado.",
            "antecedentes_escolares": "Viene del Jardín Infantil 'Los Pinos'."
        }
         */
        Route::put('/aspirante', [AdmissionsController::class, 'actualizarRegistroAspirante']);

        Route::get('/aspirante', [AdmissionsController::class, 'mostrarInformacionAspiranteId']);

        /** Ejemplo JSON para agregar al aspirante (Datos minimos necesarios): 
        {
            "nombre_completo": "Juan Pérez García",
            "grado_aplica": "Primero de Primaria",
            "id_inscripcion": 1,
            "anio_academico": 8
        }
        */
        Route::post('/aspirante', [AdmissionsController::class, 'registrarAspirante']);

        Route::delete('/aspirante', [AdmissionsController::class, 'eliminarRegistroAspirante']);

        /**
         * Ejemplo JSON para registrar a un acudiente (Datos minimos):
        {
            "id_aspirante": 1,
            "id_inscripcion": 1,
            "tipo_parentesco": "Acudiente",
            "nombre_completo": "María López"
        }
         */
        Route::post('/acudiente', [AdmissionsController::class, 'agregarFamiliarAspirante']);

        /**
         * Ejemplo para actualizar a un acudiente completamente:
        {
            "id": 1,
            "aspirante_id": 1,
            "id_inscripcion": 1,
            "tipo_parentesco": "Padre",
            "nombre_completo": "María López",
            "documento_identidad": "52456789",
            "lugar_expedicion_doc": "Medellín",
            "estado_civil": "Casada",
            "idiomas": "Español",
            "direccion_residencia": "Calle 45 # 20 - 10",
            "telefono_fijo": "6044589632",
            "celular": "3001234567",
            "email": "laura.gomez@gmail.com",
            "profesion": "Psicóloga",
            "empresa_labora": "Centro Integral Familiar",
            "cargo_ocupacion": "Psicóloga Clínica",
            "telefono_oficina": "6047894563",
            "fecha_registro": "2026-05-08T10:40:42.000000Z"
        }
         */
        Route::put("/acudiente", [AdmissionsController::class, 'actualizarFamiliarAspirante']);

        Route::post('/correoInformativo', [AdmissionsController::class, 'correoInformativoSolicitudInicial']);

        Route::post("/informacionMedica", [AdmissionsController::class, 'agregarInformacionMedicaAspirante']);
        Route::put("/informacionMedica", [AdmissionsController::class, 'actualizarInformacionMedicaAspirante']);
        Route::delete("/informacionMedica", [AdmissionsController::class, 'eliminarInformacionMedicaAspirante']);

        Route::post("/admincionDocumentos", [AdmissionsController::class, 'subirDocumentoInscripcion']);

        Route::post('/testArchivo', [AdmissionsController::class, 'testArchivoGuardar']);
        Route::delete('/testArchivo', [AdmissionsController::class, 'testArchivoEliminar']);
    });
});
