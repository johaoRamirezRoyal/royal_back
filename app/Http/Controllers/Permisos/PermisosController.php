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
}
