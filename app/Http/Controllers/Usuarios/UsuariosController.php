<?php

namespace App\Http\Controllers\Usuarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Usuarios\ActualizarUsuarioRequest;
use App\Http\Requests\Usuarios\RegistrarUsuarioRequest;
use App\Http\Resources\Usuarios\UsuarioInscripcionResource;
use App\Services\Niveles\NivelesServices;
use App\Services\Perfiles\PerfilesServices;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UsuariosController extends Controller
{
    // Opción "/users" (2) en el frontend: gatea crear/editar usuarios y cambiar su
    // estado. Sin este chequeo, cualquier autenticado podía crear un usuario con
    // cualquier perfil (incluido Super Admin) o desactivar cuentas ajenas con un POST/PUT
    // directo, sin tener la opción otorgada.
    private const OPCION_USUARIOS = 2;

    protected $service_usuarios;

    protected $service_perfiles;

    protected $service_niveles;

    public function __construct(UsuariosServices $usuariosServices, PerfilesServices $perfilesServices, NivelesServices $nivelesServices)
    {
        $this->service_usuarios = $usuariosServices;
        $this->service_perfiles = $perfilesServices;
        $this->service_niveles = $nivelesServices;
    }

    /**
     * Chequeo server-side del permiso, no solo ocultar el botón en el frontend —
     * cualquier intento directo a estos endpoints sin el permiso se rechaza acá.
     */
    private function sinAcceso(Request $request)
    {
        $tienePermiso = $this->service_usuarios->tienePermiso(self::OPCION_USUARIOS, $request->user()->perfil)['permiso'] ?? false;

        return $tienePermiso ? null : $this->error('No tienes permiso para gestionar usuarios', 403);
    }

    // GET /usuarios
    public function mostrarTodosUsuariosActivos()
    {
        return response()->json(
            $this->service_usuarios->mostrarTodosUsuariosActivos(),
            200
        );
    }

    // GET /usuariosPaginados
    public function mostrarTodosUsuariosActivoPaginado(Request $request)
    {
        $per_page = $request->input('per-page', 10);
        $response = $this->service_usuarios->mostrarTodosUsuariosActivoPaginado($per_page);

        if ($response['error']) {
            return $this->error($response['message']);
        }

        return $this->paginatedResponse($response);
    }

    public function mostrarTodosUsuariosPaginado(Request $request)
    {
        $per_page = $request->input('per-page', 10);
        $response = $this->service_usuarios->mostrarTodosUsuariosPaginado($per_page);

        if ($response['error']) {
            return $this->error($response['message']);
        }

        return $this->paginatedResponse($response);
    }

    public function mostrarUsuariosPaginados(Request $request)
    {
        $per_page = $request->input('per-page', 10);
        $perfil_filtro = $request->input('perfiles');
        $nivel_filtro = $request->input('niveles');
        $curso_filtro = $request->input('cursos');
        $busqueda = $request->input('s', $request->input('busqueda'));
        $estado = $request->input('estado');
        $sort = $request->input('sort', 'nombre');
        $dir = $request->input('dir', 'asc');

        $response = $this->service_usuarios->mostrarUsuariosPaginados(
            (int) $per_page,
            $perfil_filtro,
            $nivel_filtro,
            $busqueda,
            $estado,
            $sort,
            $dir,
            $curso_filtro
        );

        if ($response['error']) {
            return $this->error($response['message']);
        }

        return $this->paginatedResponse($response);
    }

    public function mostrarTodosUsuarios()
    {
        return response()->json(
            $this->service_usuarios->mostrarTodosUsuarios(),
            200
        );
    }

    // GET /usuarios/select — listado liviano (id_user, nom_user, documento, perfil) para
    // poblar selects; trabajadores + estudiantes, excluye Proveedor (17) y Acudiente (6).
    public function usuariosSelectInventario()
    {
        return response()->json(
            $this->service_usuarios->usuariosSelectInventario(),
            200
        );
    }

    public function filtrarUsuarios(Request $request)
    {
        $datos = $request->all();
        $search = $request->input('s', '');
        $sort = $request->input('sort', '');
        $dir = $request->input('dir', 'asc');
        $per_page = $request->input('per-page', 10);

        Log::debug('Datos de filtro de usuarios', $datos);

        $filtro = $this->service_usuarios->filtrarUsuarios($datos, $search, $sort, $dir, $per_page);

        if ($filtro['error']) {
            return response()->json([
                'error' => true,
                'message' => $filtro['message'],
            ], 500);
        }

        return response()->json([
            'error' => false,
            'data' => json_decode(json_encode($filtro['data'], JSON_INVALID_UTF8_SUBSTITUTE)),
        ], 200);
    }

    public function tienePermiso(Request $request)
    {
        $opcion = $request->input('opt');
        $perfil = $request->input('per');

        $permiso = $this->service_usuarios->tienePermiso($opcion, $perfil);
        $code = ($permiso['permiso']) ? 200 : 405;

        if ($permiso['error']) {
            return response()->json([
                'error' => true,
                'message' => $permiso['message'],
            ], $code);
        }

        return response()->json($permiso, $code);
    }

    public function mostrarInfoUsuarioId($id)
    {
        $usuario_id = $id;

        if (empty($usuario_id)) {
            return response()->json([
                'error' => true,
                'message' => 'Debe agregar el ID del usuario',
            ], 400);
        }

        $response = $this->service_usuarios->mostrarInfoUsuarioId($usuario_id);

        if ($response['error']) {
            return response()->json([
                'error' => true,
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        return response()->json([
            'error' => false,
            'message' => 'Usuario encontrado',
            'data' => $response['usuario'],
        ]);
    }

    public function agregarUsuario(RegistrarUsuarioRequest $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $data = $request->toUsuarioFormatCreate();

        // La tabla legacy requiere estos campos en algunos entornos; si el
        // frontend no los envía, los rellenamos antes del insert.
        $data['fechareg'] = $data['fechareg'] ?? now();
        $data['user_log'] = auth('api')->id() ?? $data['user_log'] ?? 1;

        $response = $this->service_usuarios->agregarUsuario($data);

        if ($response['error']) {
            Log::error('Error creando usuario', ['message' => $response['message'], 'data' => $data]);

            return response()->json([
                'error' => true,
                'message' => $response['message'],
            ], 500);
        }

        return response()->json([
            'error' => false,
            'message' => 'Usuario agregado',
            'data' => $response['usuario'],
        ]);
    }

    public function actualizarUsuarios(ActualizarUsuarioRequest $request, $id)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $usuario_id = $id;
        $data = $request->toUsuarioFormatCreate();

        if (empty($usuario_id)) {
            return response()->json([
                'error' => true,
                'message' => 'Debes insertar el id del usuario',
            ]);
        }

        $response = $this->service_usuarios->actualizarUsuarios($usuario_id, $data);

        if ($response['error']) {
            return response()->json([
                'error' => true,
                'message' => $response['message'],
            ]);
        }

        return response()->json([
            'error' => false,
            'message' => 'Usuario actualizado',
            'data' => $response['usuario'],
        ]);
    }

    public function actualizarEstadoUsuarios(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $ids = $request->input('ids', []);
        $estado = $request->input('estado', 'activo');

        if (empty($ids) || ! is_array($ids)) {
            return response()->json([
                'error' => true,
                'message' => 'Debes proporcionar un array de IDs de usuarios a cambiar el estado',
                400,
            ]);
        }

        $response = $this->service_usuarios->actualizarEstadoUsuarios($ids, $estado);

        if ($response['error']) {
            return response()->json([
                'error' => true,
                'message' => $response['message'],
                500,
            ]);
        }

        return response()->json([
            'error' => $response['error'],
            'message' => $response['message'],
        ], 200);
    }

    // ===================== NIVELES ========================
    public function mostrarTodosNiveles(Request $request)
    {
        $response = $this->service_niveles->mostrarTodosNiveles($request->boolean('solo_academicos'));

        if ($response['error']) {
            return response()->json([
                'error' => true,
                'message' => $response['message'],
            ], 404);
        }

        return response()->json([
            'error' => false,
            'data' => $response['data'],
        ], 200);
    }

    public function mostrarTodosNivelesAcademicos()
    {
        $response = $this->service_niveles->mostrarTodosNivelesAcademicos();

        if ($response['error']) {
            return response()->json([
                'error' => true,
                'message' => $response['message'],
            ], 404);
        }

        return response()->json([
            'error' => false,
            'data' => $response['data'],
        ], 200);
    }

    // ==================== PERFILES ========================
    public function mostrarTodosPerfiles()
    {
        $response = $this->service_perfiles->mostrarTodosPerfiles();

        if ($response['error']) {
            return response()->json([
                'error' => true,
                'message' => $response['data'],
            ], 404);
        }

        return response()->json([
            'error' => false,
            'data' => $response['data'],
        ], 200);
    }

    public function mostrarUsuariosConInscripciones(Request $request)
    {
        $per_page = $request->input('per-page', 10);
        $response = $this->service_usuarios->mostrarUsuariosConInscripciones($per_page);

        if ($response['error']) {
            return $this->error($response['message']);
        }

        return $this->paginatedResponse(
            $response,
            UsuarioInscripcionResource::class
        );
    }

    // GET /usuarios/firma
    public function verFirma(Request $request)
    {
        $id_user = $request->user()->id_user;

        $response = $this->service_usuarios->verFirmaUsuario($id_user);

        return response()->json(['firma_url' => $response['firma_url'] ?? null]);
    }

    // POST /usuarios/firma (multipart, campo firma)
    public function subirFirma(Request $request)
    {
        $request->validate(['firma' => 'required|file']);

        $id_user = $request->user()->id_user;
        $response = $this->service_usuarios->subirFirmaUsuario($id_user, $request->file('firma'));

        if ($response['error']) {
            return response()->json(['error' => true, 'message' => $response['message']], 400);
        }

        return response()->json(['firma_url' => $response['firma_url']]);
    }
}
