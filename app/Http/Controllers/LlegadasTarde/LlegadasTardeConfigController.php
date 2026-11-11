<?php

namespace App\Http\Controllers\LlegadasTarde;

use App\Http\Controllers\Controller;
use App\Services\LlegadasTardeEstudiantes\LlegadasTardeConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\Permiso;
use App\Services\Usuarios\PermisosPerfil;

class LlegadasTardeConfigController extends Controller
{
    // Ver LlegadasTardeController::OPCION_ACCESO_COMPLETO.
    private const OPCION_ACCESO_COMPLETO = Permiso::GESTION_ACADEMICA;

    public function __construct(
        private LlegadasTardeConfigService $configService,
    ) {}

    public function index(): JsonResponse
    {
        return $this->apiResponse($this->configService->obtenerConfiguracion());
    }

    public function update(Request $request): JsonResponse
    {
        $tieneAccesoCompleto = PermisosPerfil::tiene($request, self::OPCION_ACCESO_COMPLETO);

        if (!$tieneAccesoCompleto) {
            return $this->error('No tienes permiso para modificar esta configuración', 403);
        }

        $request->validate([
            'hora_limite' => 'sometimes|date_format:H:i',
            'cantidad_limite' => 'sometimes|integer|min:1',
            'notificar_coordinador' => 'sometimes|boolean',
            // Solo dígitos, con + y separadores opcionales; null/vacío desactiva el WhatsApp.
            'whatsapp_destino' => ['sometimes', 'nullable', 'string', 'regex:/^\+?[\d\s-]{10,20}$/'],
        ], [
            'hora_limite.date_format' => 'La hora límite debe tener el formato HH:MM.',
            'cantidad_limite.integer' => 'La cantidad límite debe ser numérica.',
            'cantidad_limite.min' => 'La cantidad límite debe ser al menos 1.',
            'whatsapp_destino.regex' => 'El número de WhatsApp debe tener al menos 10 dígitos (con indicativo opcional).',
        ]);

        $datos = $request->only(['hora_limite', 'cantidad_limite', 'whatsapp_destino']);
        $notificarCoordinador = $request->boolean('notificar_coordinador');

        $resultado = $this->configService->actualizarConfiguracion($datos, $notificarCoordinador);

        return $this->apiResponse($resultado);
    }
}
