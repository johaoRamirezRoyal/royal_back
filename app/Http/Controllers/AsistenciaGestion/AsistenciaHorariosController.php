<?php

namespace App\Http\Controllers\AsistenciaGestion;

use App\Http\Controllers\Controller;
use App\Services\AsistenciaTrabajadores\AsistenciaHorariosService;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AsistenciaHorariosController extends Controller
{
    // Opción de "/permisos" para "Configuración de asistencia" — solo RH y Administradores
    // la tienen asignada por defecto (ver migración 2026_08_06_010002).
    private const OPCION_CONFIGURACION = 100;

    public function __construct(
        private AsistenciaHorariosService $horariosService,
        private UsuariosServices $usuariosService,
    ) {}

    /**
     * Chequeo server-side del permiso, no solo ocultar el link en el sidebar — cualquier
     * intento directo a estos endpoints sin el permiso se rechaza acá.
     */
    private function sinPermiso(Request $request): ?JsonResponse
    {
        $puedeConfigurar = $this->usuariosService->tienePermiso(self::OPCION_CONFIGURACION, $request->user()->perfil)['permiso'] ?? false;

        return $puedeConfigurar ? null : $this->error('No tienes permiso para acceder a esta configuración', 403);
    }

    public function index(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinPermiso($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->horariosService->listarHorarios());
    }

    public function store(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinPermiso($request)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:100',
            'grupo_id' => 'nullable|integer',
            'hora_llegada_esperada' => 'required|date_format:H:i',
            'hora_salida_esperada' => 'required|date_format:H:i',
            'activo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        return $this->apiResponse($this->horariosService->crearHorario($validator->validated()));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinPermiso($request)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|max:100',
            'grupo_id' => 'sometimes|nullable|integer',
            'hora_llegada_esperada' => 'sometimes|date_format:H:i',
            'hora_salida_esperada' => 'sometimes|date_format:H:i',
            'activo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        return $this->apiResponse($this->horariosService->actualizarHorario($id, $validator->validated()));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinPermiso($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->horariosService->eliminarHorario($id));
    }

    public function storeBanda(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinPermiso($request)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:100',
            'hora_desde' => 'nullable|date_format:H:i',
            'hora_hasta' => 'nullable|date_format:H:i',
            'orden' => 'sometimes|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        return $this->apiResponse($this->horariosService->crearBanda($id, $validator->validated()));
    }

    public function updateBanda(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinPermiso($request)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|max:100',
            'hora_desde' => 'sometimes|nullable|date_format:H:i',
            'hora_hasta' => 'sometimes|nullable|date_format:H:i',
            'orden' => 'sometimes|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        return $this->apiResponse($this->horariosService->actualizarBanda($id, $validator->validated()));
    }

    public function destroyBanda(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinPermiso($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->horariosService->eliminarBanda($id));
    }

    public function reordenarBandas(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinPermiso($request)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'bandas' => 'required|array|min:1',
            'bandas.*.id' => 'required|integer|exists:asistencia_puntualidad_bandas,id',
            'bandas.*.orden' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        return $this->apiResponse($this->horariosService->reordenarBandas($request->input('bandas')));
    }
}
