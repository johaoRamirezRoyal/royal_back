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
