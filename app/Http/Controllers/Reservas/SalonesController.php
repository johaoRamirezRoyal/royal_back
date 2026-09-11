<?php

namespace App\Http\Controllers\Reservas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reservas\HoraRequest;
use App\Http\Requests\Reservas\SalonRequest;
use App\Models\Reservas\ConfiguracionReservas;
use App\Models\Reservas\Horas;
use App\Models\Reservas\Salones;
use App\Services\Reservas\ReservasServices;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalonesController extends Controller
{
    // Opción "/reservas/salones" (40) en el frontend: gatea administrar el catálogo de
    // salones (crear/editar/eliminar), NO listarlos — `listarSalones`/`listarHoras` los
    // sigue necesitando cualquier autenticado para reservar (CreateReservaModal, en
    // /reservas, no tiene ni tiene por qué tener este permiso: reservar es de acceso
    // general, administrar el catálogo de salones no).
    private const OPCION_SALONES = 40;

    public function __construct(
        private ReservasServices $reservasServices,
        private UsuariosServices $usuariosService,
    ) {}

    private function sinAcceso(Request $request): ?JsonResponse
    {
        $tienePermiso = $this->usuariosService->tienePermiso(self::OPCION_SALONES, $request->user()->perfil)['permiso'] ?? false;

        return $tienePermiso ? null : $this->error('No tienes permiso para administrar salones', 403);
    }

    public function listarSalones()
    {
        return $this->apiResponse([
            'error' => false,
            'message' => 'Salones obtenidos correctamente.',
            'data' => Salones::activo()->get(['id', 'nombre', 'portatil', 'sonido'])
        ]);
    }

    public function crearSalon(SalonRequest $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse(
            $this->reservasServices->crearSalon($request->validated())
        );
    }

    public function actualizarSalon(SalonRequest $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse(
            $this->reservasServices->actualizarSalon($request->validated(), $id)
        );
    }

    public function eliminarSalon(Request $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse(
            $this->reservasServices->eliminarSalon($id)
        );
    }

    public function listarHoras()
    {
        return $this->apiResponse([
            'error' => false,
            'message' => 'Horas obtenidas correctamente.',
            'data' => Horas::where('activo', 1)->get(['id', 'horas'])
        ]);
    }

    // Mismo gate que el catálogo de salones (opción 40): "definir las horas de reserva"
    // es parte de la misma administración del módulo, no amerita una opción aparte.
    public function crearHora(HoraRequest $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse(
            $this->reservasServices->crearHora($request->validated())
        );
    }

    public function actualizarHora(HoraRequest $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse(
            $this->reservasServices->actualizarHora($request->validated(), $id)
        );
    }

    public function eliminarHora(Request $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse(
            $this->reservasServices->eliminarHora($id)
        );
    }

    /**
     * Ventana de anticipación (días) para poder reservar — editable acá para no tocar el
     * servidor, mismo patrón que InstitucionAdminController::configuracion()/actualizarConfiguracion().
     */
    public function configuracion(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse([
            'error' => false,
            'message' => 'Configuración obtenida correctamente.',
            'data' => ConfiguracionReservas::actual(),
        ]);
    }

    public function actualizarConfiguracion(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $request->validate(
            [
                'dias_min_anticipacion' => 'sometimes|integer|min:1|max:365',
                'dias_max_anticipacion' => 'sometimes|integer|min:1|max:365',
            ],
            [
                'dias_min_anticipacion.integer' => 'Los días mínimos de anticipación deben ser un número entero.',
                'dias_min_anticipacion.min' => 'Los días mínimos de anticipación deben ser al menos 1 (no se permite reservar el mismo día).',
                'dias_max_anticipacion.integer' => 'Los días máximos de anticipación deben ser un número entero.',
                'dias_max_anticipacion.min' => 'Los días máximos de anticipación deben ser al menos 1.',
            ],
        );

        $config = ConfiguracionReservas::actual();
        $datos = $request->only(['dias_min_anticipacion', 'dias_max_anticipacion']);

        $diasMin = $datos['dias_min_anticipacion'] ?? $config->dias_min_anticipacion;
        $diasMax = $datos['dias_max_anticipacion'] ?? $config->dias_max_anticipacion;

        if ($diasMax < $diasMin) {
            return $this->apiResponse([
                'error' => true,
                'message' => 'Los días máximos de anticipación no pueden ser menores que los mínimos.',
                'data' => [],
            ]);
        }

        $config->update($datos);

        return $this->apiResponse([
            'error' => false,
            'message' => 'Configuración actualizada correctamente.',
            'data' => $config->fresh(),
        ]);
    }
}
