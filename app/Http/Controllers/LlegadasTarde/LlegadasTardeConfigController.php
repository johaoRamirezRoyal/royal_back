<?php

namespace App\Http\Controllers\LlegadasTarde;

use App\Http\Controllers\Controller;
use App\Services\LlegadasTardeEstudiantes\LlegadasTardeConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LlegadasTardeConfigController extends Controller
{
    public function __construct(private LlegadasTardeConfigService $configService) {}

    public function index(): JsonResponse
    {
        return $this->apiResponse($this->configService->obtenerConfiguracion());
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'hora_limite' => 'sometimes|date_format:H:i',
            'cantidad_limite' => 'sometimes|integer|min:1',
            'notificar_coordinador' => 'sometimes|boolean',
        ], [
            'hora_limite.date_format' => 'La hora límite debe tener el formato HH:MM.',
            'cantidad_limite.integer' => 'La cantidad límite debe ser numérica.',
            'cantidad_limite.min' => 'La cantidad límite debe ser al menos 1.',
        ]);

        $datos = $request->only(['hora_limite', 'cantidad_limite']);
        $notificarCoordinador = $request->boolean('notificar_coordinador');

        $resultado = $this->configService->actualizarConfiguracion($datos, $notificarCoordinador);

        return $this->apiResponse($resultado);
    }
}
