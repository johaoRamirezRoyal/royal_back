<?php

namespace App\Http\Controllers\Inventarios;

use App\Http\Controllers\Concerns\VerificaPermisos;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventario\ProgramacionAvanceRequest;
use App\Http\Requests\Inventario\ProgramacionMantFiltroRequest;
use App\Http\Requests\Inventario\ProgramacionMantRequest;
use App\Services\inventario\ProgramacionMantServices;
use App\Support\Permiso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Programación de mantenimientos (/inventario/mantenimiento/programacion).
 */
class ProgramacionMantController extends Controller
{
    use VerificaPermisos;

    public function __construct(
        private ProgramacionMantServices $programacion,
    ) {}

    /**
     * GET /api/inventario/mantenimiento/programacion?per-page=&s=&id_anio=&id_periodo=&mes=&estado=&tipo=
     */
    public function listar(ProgramacionMantFiltroRequest $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::INVENTARIO)) {
            return $rechazo;
        }

        return $this->paginatedResponse(
            $this->programacion->listar($request->filtros(), (int) $request->input('per-page', 20))
        );
    }

    /**
     * GET /api/inventario/mantenimiento/programacion/estadisticas — mismos filtros que listar.
     */
    public function estadisticas(ProgramacionMantFiltroRequest $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::INVENTARIO)) {
            return $rechazo;
        }

        return $this->apiResponse($this->programacion->estadisticas($request->filtros()));
    }

    /**
     * GET /api/inventario/mantenimiento/programacion/calendario?desde=&hasta= — sin paginar.
     */
    public function calendario(ProgramacionMantFiltroRequest $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::INVENTARIO)) {
            return $rechazo;
        }

        return $this->apiResponse($this->programacion->calendario($request->filtros()));
    }

    public function crear(ProgramacionMantRequest $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::INVENTARIO)) {
            return $rechazo;
        }

        return $this->apiResponse($this->programacion->crear($request->validated(), $request->user()->id_user));
    }

    public function actualizar(ProgramacionMantRequest $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::INVENTARIO)) {
            return $rechazo;
        }

        return $this->apiResponse($this->programacion->actualizar($id, $request->validated(), $request->user()->id_user));
    }

    public function eliminar(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::INVENTARIO)) {
            return $rechazo;
        }

        return $this->apiResponse($this->programacion->eliminar($id));
    }

    public function avances(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::INVENTARIO)) {
            return $rechazo;
        }

        return $this->apiResponse($this->programacion->avances($id));
    }

    /**
     * POST /api/inventario/mantenimiento/programacion/{id}/avances — multipart (archivo opcional).
     */
    public function registrarAvance(ProgramacionAvanceRequest $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::INVENTARIO)) {
            return $rechazo;
        }

        return $this->apiResponse($this->programacion->registrarAvance(
            $id,
            $request->validated(),
            $request->user()->id_user,
            $request->file('archivo')
        ));
    }
}
