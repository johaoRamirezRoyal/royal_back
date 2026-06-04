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

use function Illuminate\Log\log;

class UsuariosController extends Controller
{
    protected $service_usuarios;

    protected $service_perfiles;

    protected $service_niveles;

    public function __construct(UsuariosServices $usuariosServices, PerfilesServices $perfilesServices, NivelesServices $nivelesServices)
    {
        $this->service_usuarios = $usuariosServices;
        $this->service_perfiles = $perfilesServices;
        $this->service_niveles = $nivelesServices;
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

        return response()->json(
            $this->service_usuarios->mostrarTodosUsuariosActivoPaginado($per_page),
            200
        );
    }

    public function mostrarTodosUsuariosPaginado(Request $request)
    {
        $per_page = $request->input('per-page', 10);

        return response()->json(
            $this->service_usuarios->mostrarTodosUsuariosPaginado($per_page),
            200
        );
    }

    public function mostrarTodosUsuarios()
    {
        return response()->json(
            $this->service_usuarios->mostrarTodosUsuarios(),
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

        log($datos);

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
        $data = $request->toUsuarioFormatCreate();

        $response = $this->service_usuarios->agregarUsuario($data);

        if ($response['error']) {
            return response()->json([
                'error' => true,
                'message' => $response['message'],
            ]);
        }

        return response()->json([
            'error' => false,
            'message' => 'Usuario agregado',
            'data' => $response['usuario'],
        ]);
    }

    public function actualizarUsuarios(ActualizarUsuarioRequest $request, $id)
    {
        $usuario_id = $id;
        $data = $request->toUsuarioFormatUpdate();

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
    public function mostrarTodosNiveles()
    {
        $response = $this->service_niveles->mostrarTodosNiveles();

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

    // -------------------- CONSULTAR USUARIOS Y SUS INSCRIPCIONES ========================
    public function mostrarUsuariosConInscripciones(Request $request)
    {
        $per_page = $request->input('per-page', 10);

        $response = $this->service_usuarios->mostrarUsuariosConInscripciones($per_page);

        if ($response['error']) {
            return $this->apiResponse($response);
        }

        return $this->apiResponse([
            'error' => $response['error'],
            'message' => $response['message'],
            'data' => UsuarioInscripcionResource::collection($response['data']),
        ]);
    }
}
