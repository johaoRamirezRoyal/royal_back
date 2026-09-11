<?php

namespace App\Http\Controllers\Areas;

use App\Http\Controllers\Controller;
use App\Services\Areas\AreasServices;
use App\Services\inventario\InventarioServices;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Acciones administrativas masivas de Áreas Comunes que cruzan `areas`/`inventario`
 * — se quedan aparte de AreasController/InventariosController porque su permiso no
 * es OPCION_INVENTARIO (12) sino "Administrador areas comunes" (108, cron_opciones
 * bajo id_modulo=6 "Zonas" — ver migración 2026_09_10_150000_seed_opciones_areas_comunes).
 */
class AreasComunesController extends Controller
{
    private const OPCION_ADMIN_AREAS_COMUNES = 108;
    private const OPCION_USO_AREAS_COMUNES = 109;

    public function __construct(
        private AreasServices $areasService,
        private InventarioServices $inventarioService,
        private UsuariosServices $usuariosService,
    ) {
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

    public function asignarAreasBloque(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN_AREAS_COMUNES)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'id_bloque' => ['required', 'integer', 'exists:bloques,id'],
            'ids' => ['present', 'array'],
            'ids.*' => ['integer', 'exists:areas,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $resultado = $this->areasService->asignarAreasBloque(
            $request->input('id_bloque'),
            $request->input('ids', []),
        );

        return response()->json($resultado, $resultado['error'] ? 400 : 200);
    }

    public function reclasificarInventario(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN_AREAS_COMUNES)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:inventario,id'],
            'id_categoria' => ['required', 'integer', 'exists:categoria,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $resultado = $this->inventarioService->reclasificarAreaComun(
            $request->input('ids'),
            $request->input('id_categoria'),
            $request->user()->id_user,
        );

        return response()->json($resultado, $resultado['error'] ? 400 : 200);
    }

    /**
     * Check semestral de inventario (migración del legacy chek_zonas) — tanto
     * admin como uso diario de Áreas Comunes pueden registrarlo.
     */
    public function registrarCheck(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN_AREAS_COMUNES, self::OPCION_USO_AREAS_COMUNES)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:inventario,id'],
            'id_anio' => ['nullable', 'integer', 'exists:anio_escolar,id'],
            'periodo' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $resultado = $this->inventarioService->registrarCheckInventario(
            $request->input('ids'),
            $request->input('id_anio'),
            $request->input('periodo'),
            $request->user()->id_user,
        );

        return response()->json($resultado, $resultado['error'] ? 400 : 200);
    }
}
