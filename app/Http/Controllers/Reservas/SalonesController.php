<?php

namespace App\Http\Controllers\Reservas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reservas\SalonRequest;
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
}
