<?php

namespace App\Http\Controllers\AsistenciaGestion;

use App\Http\Controllers\Controller;
use App\Http\Requests\AsistenciaGestion\FiltroAsistenciaGestionRequest;
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
