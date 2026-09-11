<?php

namespace App\Http\Controllers\Areas;

use App\Http\Controllers\Controller;
use App\Services\Areas\BloquesServices;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BloquesController extends Controller
{
    // cron_opciones bajo id_modulo=6 (Zonas) — ver migración
    // 2026_09_10_150000_seed_opciones_areas_comunes.
    private const OPCION_ADMIN_AREAS_COMUNES = 108;
    private const OPCION_USO_AREAS_COMUNES = 109;

    protected $service_bloques;

    public function __construct(
        BloquesServices $bloquesServices,
        private UsuariosServices $usuariosService,
    ) {
        $this->service_bloques = $bloquesServices;
    }

    private function sinAcceso(Request $request, int ...$opciones): ?JsonResponse
    {
        $perfil = $request->user()->perfil;

        foreach ($opciones as $opcion) {
            if ($this->usuariosService->tienePermiso($opcion, $perfil)['permiso'] ?? false) {
                return null;
            }
        }

        return response()->json([
            'error' => true,
            'message' => 'No tienes permiso para esta acción',
        ], 403);
    }

    private function esAdministrador(Request $request): bool
    {
        return $this->usuariosService->tienePermiso(self::OPCION_ADMIN_AREAS_COMUNES, $request->user()->perfil)['permiso'] ?? false;
    }

    public function obtenerTodosLosBloques(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN_AREAS_COMUNES, self::OPCION_USO_AREAS_COMUNES)) {
            return $rechazo;
        }

        // Quien solo tiene "Uso areas comunes" ve únicamente los bloques de su
        // propio nivel — reforzado acá, no solo ocultado en el frontend.
        $idNivel = $this->esAdministrador($request) ? null : $request->user()->id_nivel;

        $bloques = $this->service_bloques->obtenerTodosLosBloques($idNivel);

        if ($bloques['error']) {
            return response()->json([
                'error' => true,
                'message' => $bloques['message'],
            ]);
        }

        return response()->json([
            'error' => false,
            'data' => $bloques['data'],
        ]);
    }

    public function crearBloque(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN_AREAS_COMUNES)) {
            return $rechazo;
        }

        $bloque = $this->service_bloques->crearBloque($request->all());

        if ($bloque['error']) {
            return response()->json([
                'error' => true,
                'message' => $bloque['message'],
            ]);
        }

        return response()->json([
            'error' => false,
            'data' => $bloque['data'],
        ]);
    }

    public function actualizarBloque(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN_AREAS_COMUNES)) {
            return $rechazo;
        }

        $id = $request->input('id');
        $datos = $request->except('id');

        $actualizacion = $this->service_bloques->actualizarBloque($id, $datos);

        if ($actualizacion['error']) {
            return response()->json([
                'error' => true,
                'message' => $actualizacion['message'],
            ]);
        }

        return response()->json([
            'error' => false,
            'message' => $actualizacion['message'],
        ]);
    }

    public function desactivarBloques(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN_AREAS_COMUNES)) {
            return $rechazo;
        }

        $ids = $request->input('ids');
        $estado = $request->input('estado');

        $resultado = $this->service_bloques->desactivarBloques($ids, $estado);

        if ($resultado['error']) {
            return response()->json([
                'error' => true,
                'message' => $resultado['message'],
            ]);
        }

        return response()->json([
            'error' => false,
            'message' => $resultado['message'],
        ]);
    }

    public function asignarResponsables(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN_AREAS_COMUNES)) {
            return $rechazo;
        }

        $idBloque = $request->input('id_bloque');
        $idUsuarios = $request->input('id_usuarios', []);

        $resultado = $this->service_bloques->asignarResponsables($idBloque, $idUsuarios);

        if ($resultado['error']) {
            return response()->json([
                'error' => true,
                'message' => $resultado['message'],
            ]);
        }

        return response()->json([
            'error' => false,
            'message' => $resultado['message'],
        ]);
    }

    public function usuariosAsignablesBloque(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN_AREAS_COMUNES)) {
            return $rechazo;
        }

        $usuarios = $this->service_bloques->usuariosAsignablesBloque();

        if ($usuarios['error']) {
            return response()->json([
                'error' => true,
                'message' => $usuarios['message'],
            ]);
        }

        return response()->json([
            'error' => false,
            'data' => $usuarios['data'],
        ]);
    }
}
