<?php

namespace App\Http\Controllers\Permisos;

use App\Services\Permisos\PermisosService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PermisosController extends Controller

{
    protected $services_permisos;

    public function __construct(PermisosService $services_permisos)
    {
        $this->services_permisos = $services_permisos;
    }

    public function verPermisosPorPerfil(Request $request)
    {
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
        $validated = $request->validate([
            'id_opcion' => 'required|integer|exists:cron_opciones,id',
            'id_perfil' => 'required|integer|exists:perfiles,id_perfil',
            'user_log' => 'required|integer|exists:usuarios,id_user'
        ]);

        $datos = [
            'id_opcion' => $validated['id_opcion'],
            'id_perfil' => $validated['id_perfil'],
            'user_log' => $validated['user_log'],
            'activo' => 1
        ];

        $response = $this->services_permisos->crearPermiso($datos);

        return $this->apiResponse($response);
    }

    public function eliminarPermiso(Request $request)
    {
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
        $validated = $request->validate([
            'perfiles' => 'nullable|array',
            'perfiles.*' => 'integer|exists:perfiles,id_perfil'
        ]);

        $perfiles = $validated['perfiles'] ?? null;

        $response = $this->services_permisos->verOpcionesPorPerfil($perfiles);

        return $this->apiResponse($response);
    }

    public function verTodosLosPermisosOpciones()
    {
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
