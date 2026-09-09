<?php

namespace App\Http\Controllers\Administrativo;

use App\Http\Controllers\Controller;
use App\Services\AnioEscolar\AnioEscolarServices;
use App\Services\AnioEscolar\PeriodoServices;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Módulo administrativo "Año Escolar y Periodos" (opción propia, ver migración
 * 2026_09_09_130000_seed_opcion_anio_escolar_periodos) — CRUD de `anio_escolar` (reusa
 * AnioEscolarServices, que ya tenía crear/activar/desactivar para Gestión Académica) y de
 * `periodos` (crear/editar/activar/marcar-en-curso, antes sin CRUD — ver PeriodoServices).
 */
class AnioEscolarPeriodoController extends Controller
{
    // Verificado contra AUTO_INCREMENT de `cron_opciones` (107) al momento de escribir esto,
    // antes de correr la migración 2026_09_09_130000_seed_opcion_anio_escolar_periodos —
    // si otra migración inserta una opción antes de esa, este id quedará desalineado (ver
    // el bug ya documentado de Instituciones, 104 vs 106 real, en el AGENTS.md del repo).
    // Confirmar `SELECT id FROM cron_opciones WHERE nombre = 'Año Escolar y Periodos'`
    // después de migrar en cada entorno antes de confiar en este literal.
    private const OPCION = 107;

    public function __construct(
        private AnioEscolarServices $anioEscolarServices,
        private PeriodoServices $periodoServices,
        private UsuariosServices $usuariosService,
    ) {
    }

    private function sinAcceso(Request $request): ?JsonResponse
    {
        $perfil = $request->user()->perfil;

        if ($this->usuariosService->tienePermiso(self::OPCION, $perfil)['permiso'] ?? false) {
            return null;
        }

        return $this->error('No tienes permiso para esta acción', 403);
    }

    // ── Años escolares ──────────────────────────────────────────────────────

    public function listarAnios(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return response()->json($this->anioEscolarServices->obtenerAniosEscolares());
    }

    public function crearAnio(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'anio_inicio' => 'required|integer|digits:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $resultado = $this->anioEscolarServices->crearAnioEscolarManual((int) $request->input('anio_inicio'));

        return response()->json($resultado, $resultado['error'] ? 400 : 200);
    }

    public function actualizarEstadoAnio(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'activo' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $resultado = $this->anioEscolarServices->actualizarEstadoAnioEscolar($id, (bool) $request->input('activo'));

        return response()->json($resultado, $resultado['error'] ? 400 : 200);
    }

    // ── Periodos ────────────────────────────────────────────────────────────

    public function listarPeriodos(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $resultado = $this->periodoServices->listar($request->only(['activo', 'id_anio']));

        return response()->json($resultado);
    }

    public function crearPeriodo(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'numero' => 'required|string|max:200',
            'id_anio' => 'required|integer|exists:anio_escolar,id',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'activo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $resultado = $this->periodoServices->crear($validator->validated());

        return response()->json($resultado, $resultado['error'] ? 400 : 200);
    }

    public function actualizarPeriodo(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'numero' => 'sometimes|string|max:200',
            'id_anio' => 'sometimes|integer|exists:anio_escolar,id',
            'fecha_inicio' => 'sometimes|date',
            'fecha_fin' => 'sometimes|date|after_or_equal:fecha_inicio',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $resultado = $this->periodoServices->actualizar($id, $validator->validated());

        return response()->json($resultado, $resultado['error'] ? 400 : 200);
    }

    public function actualizarEstadoPeriodo(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'activo' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $resultado = $this->periodoServices->actualizarEstado($id, (bool) $request->input('activo'));

        return response()->json($resultado, $resultado['error'] ? 400 : 200);
    }

    public function marcarPeriodoEnCurso(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $resultado = $this->periodoServices->marcarEnCurso($id);

        return response()->json($resultado, $resultado['error'] ? 400 : 200);
    }
}
