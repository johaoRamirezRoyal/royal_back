<?php

namespace App\Http\Controllers\Permisos;

use App\Services\Permisos\PermisosService;
use App\Services\Usuarios\UsuariosServices;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermisosController extends Controller

{
    // Opción "/permisos" (28) en el frontend. Este controller es el que decide qué puede
    // hacer cada perfil en todo el sistema — sin este chequeo, cualquier usuario
    // autenticado (con cualquier perfil) podía otorgarse a sí mismo cualquier opción,
    // incluida esta misma, con un solo POST directo a /api/permisos/activar-permiso.
    private const OPCION_PERMISOS = 28;

    protected $services_permisos;

    public function __construct(
        PermisosService $services_permisos,
        private UsuariosServices $usuariosService,
    ) {
        $this->services_permisos = $services_permisos;
    }

    /**
     * Chequeo server-side del permiso, no solo ocultar el módulo en el sidebar —
     * cualquier intento directo a estos endpoints sin el permiso se rechaza acá.
     */
    private function sinAcceso(Request $request): ?JsonResponse
    {
        $tienePermiso = $this->usuariosService->tienePermiso(self::OPCION_PERMISOS, $request->user()->perfil)['permiso'] ?? false;

        return $tienePermiso ? null : $this->error('No tienes permiso para gestionar los permisos del sistema', 403);
    }

    /**
     * A diferencia de sinAcceso() (exige la opción 28, otorgable a cualquier perfil),
     * crear/editar/eliminar módulos y opciones queda reservado explícitamente a Super
     * Admin (perfil 1) — una opción nueva define qué puede proteger cualquier
     * PermissionGate del sistema, no basta con tener la opción 28 asignada.
     */
    private function soloSuperAdmin(Request $request): ?JsonResponse
    {
        return $request->user()->perfil === 1
            ? null
            : $this->error('Solo un Super Admin puede gestionar módulos y opciones', 403);
    }

    public function verPermisosPorPerfil(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $id_perfil = $request->input("perfil");

        if (!$id_perfil) {
            return response()->json([
                'error' => true,
                'message' => 'Debe tener un perfil para la visualización de permisos'
            ], 401);
        }

        $datos = $this->services_permisos->verPermisosActivosPorPerfil($id_perfil);

        return response()->json($datos, 200);
    }

    public function crearPermiso(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $validated = $request->validate([
            'id_opcion' => 'required|integer|exists:cron_opciones,id',
            'id_perfil' => 'required|integer|exists:perfiles,id_perfil',
        ]);

        $datos = [
            'id_opcion' => $validated['id_opcion'],
            'id_perfil' => $validated['id_perfil'],
            // Quien queda registrado como autor del cambio es el usuario autenticado, no
            // un campo que mandaba el cliente en el body (antes se podía falsificar).
            'user_log' => $request->user()->id_user,
            'activo' => 1
        ];

        $response = $this->services_permisos->crearPermiso($datos);

        return $this->apiResponse($response);
    }

    public function eliminarPermiso(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $validated = $request->validate([
            'id_opcion' => 'required|integer|exists:cron_opciones,id',
            'id_perfil' => 'required|integer|exists:perfiles,id_perfil',
        ]);

        $datos = [
            'id_opcion' => $validated['id_opcion'],
            'id_perfil' => $validated['id_perfil']
        ];

        $response = $this->services_permisos->eliminarPermiso($datos);

        return $this->apiResponse($response);
    }

    public function verOpcionesPorPerfil(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $validated = $request->validate([
            'perfiles' => 'nullable|array',
            'perfiles.*' => 'integer|exists:perfiles,id_perfil'
        ]);

        $perfiles = $validated['perfiles'] ?? null;

        $response = $this->services_permisos->verOpcionesPorPerfil($perfiles);

        return $this->apiResponse($response);
    }

    public function verTodosLosPermisosOpciones(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $datos = $this->services_permisos->verPermisosOpciones();

        if ($datos['error']) {
            return response()->json([
                'error' => true,
                'message' => $datos['message']
            ]);
        }
        return response()->json([
            'error' => false,
            'data' => $datos['data']
        ]);
    }

    public function listarModulos(Request $request): JsonResponse
    {
        if ($rechazo = $this->soloSuperAdmin($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->services_permisos->listarModulos());
    }

    public function crearModulo(Request $request): JsonResponse
    {
        if ($rechazo = $this->soloSuperAdmin($request)) {
            return $rechazo;
        }

        $validated = $request->validate([
            'nombre' => 'required|string|max:190',
            'activo' => 'nullable|boolean',
        ]);

        return $this->apiResponse($this->services_permisos->crearModulo($validated));
    }

    public function actualizarModulo(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->soloSuperAdmin($request)) {
            return $rechazo;
        }

        $validated = $request->validate([
            'nombre' => 'nullable|string|max:190',
            'activo' => 'nullable|boolean',
        ]);

        return $this->apiResponse($this->services_permisos->actualizarModulo($id, $validated));
    }

    public function eliminarModulo(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->soloSuperAdmin($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->services_permisos->eliminarModulo($id));
    }

    public function crearOpcion(Request $request): JsonResponse
    {
        if ($rechazo = $this->soloSuperAdmin($request)) {
            return $rechazo;
        }

        $validated = $request->validate([
            'nombre' => 'required|string|max:190',
            'id_modulo' => 'required|integer|exists:cron_modulos,id',
            'activo' => 'nullable|boolean',
        ]);

        return $this->apiResponse($this->services_permisos->crearOpcion($validated));
    }

    public function actualizarOpcion(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->soloSuperAdmin($request)) {
            return $rechazo;
        }

        $validated = $request->validate([
            'nombre' => 'nullable|string|max:190',
            'id_modulo' => 'nullable|integer|exists:cron_modulos,id',
            'activo' => 'nullable|boolean',
        ]);

        return $this->apiResponse($this->services_permisos->actualizarOpcion($id, $validated));
    }

    public function eliminarOpcion(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->soloSuperAdmin($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->services_permisos->eliminarOpcion($id));
    }
}
