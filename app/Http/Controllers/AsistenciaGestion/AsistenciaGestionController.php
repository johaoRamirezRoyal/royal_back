<?php

namespace App\Http\Controllers\AsistenciaGestion;

use App\Http\Controllers\Controller;
use App\Http\Requests\AsistenciaGestion\FiltroAsistenciaGestionRequest;
use App\Http\Requests\AsistenciaGestion\GraficaAsistenciaRequest;
use App\Http\Requests\AsistenciaGestion\StoreAsistenciaGestionRequest;
use App\Services\AsistenciaTrabajadores\AsistenciaGestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AsistenciaGestionController extends Controller
{
    public function __construct(
        private AsistenciaGestionService $asistenciaService
    ) {}

    public function registrarAsistencia(StoreAsistenciaGestionRequest $request): JsonResponse
    {
        $data = $request->validated();

        $resultado = $this->asistenciaService->registrarAsistencia(
            $data['id_user'],
            $data['fecha_asistencia'],
            $data['hora_asistencia']
        );

        return $this->apiResponse($resultado);
    }

    public function obtenerAsistencia(FiltroAsistenciaGestionRequest $request): JsonResponse
    {
        $resultado = $this->asistenciaService->obtenerAsistencia($request->validated());

        return $this->apiResponse($resultado);
    }

    public function obtenerResumenPorUsuario(FiltroAsistenciaGestionRequest $request): JsonResponse
    {
        $resultado = $this->asistenciaService->obtenerResumenPorUsuario($request->validated());

        return $this->apiResponse($resultado);
    }

    public function obtenerDatosGrafica(FiltroAsistenciaGestionRequest $request): JsonResponse
    {
        $resultado = $this->asistenciaService->obtenerDatosGrafica($request->validated());

        return $this->apiResponse($resultado);
    }

    public function topUsuariosLlegadasTarde(GraficaAsistenciaRequest $request): JsonResponse
    {
        $resultado = $this->asistenciaService->topUsuariosLlegadasTarde($request->validated());

        return $this->apiResponse($resultado);
    }

    public function distribucionHorasLlegada(GraficaAsistenciaRequest $request): JsonResponse
    {
        $resultado = $this->asistenciaService->distribucionHorasLlegada($request->validated());

        return $this->apiResponse($resultado);
    }

    public function promedioHoraLlegadaPorUsuario(GraficaAsistenciaRequest $request): JsonResponse
    {
        $resultado = $this->asistenciaService->promedioHoraLlegadaPorUsuario($request->validated());

        return $this->apiResponse($resultado);
    }

    public function ultimosRegistrosUsuario(Request $request): JsonResponse
    {
        $request->validate([
            'id_usuario' => ['required', 'integer', 'exists:usuarios,id_user'],
            'limite' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [
            'id_usuario.required' => 'El ID del usuario es obligatorio.',
            'id_usuario.integer' => 'El ID del usuario debe ser numérico.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            'limite.integer' => 'El límite debe ser numérico.',
            'limite.min' => 'El límite debe ser al menos 1.',
            'limite.max' => 'El límite no puede ser mayor a 100.',
        ]);

        $resultado = $this->asistenciaService->ultimosRegistrosUsuario(
            $request->input('id_usuario'),
            $request->input('limite', 30)
        );

        return $this->apiResponse($resultado);
    }

    public function eliminarAsistencia(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:asistencia_gestion,id'],
        ], [
            'ids.required' => 'Debe proporcionar al menos un ID.',
            'ids.array' => 'Los IDs deben ser un arreglo.',
            'ids.min' => 'Debe proporcionar al menos un ID.',
            'ids.*.integer' => 'Cada ID debe ser numérico.',
            'ids.*.exists' => 'Uno o más IDs no existen en la base de datos.',
        ]);

        $resultado = $this->asistenciaService->eliminarAsistencia($request->input('ids'));

        return $this->apiResponse($resultado);
    }
}
