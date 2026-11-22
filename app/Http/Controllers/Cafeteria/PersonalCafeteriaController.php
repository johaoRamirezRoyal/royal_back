<?php

namespace App\Http\Controllers\Cafeteria;

use App\Http\Controllers\Concerns\VerificaPermisos;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cafeteria\AsignacionPersonalRequest;
use App\Services\Cafeteria\PersonalCafeteriaService;
use App\Support\Permiso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Personal de la cafetería (opción 177). Reglas en PersonalCafeteriaService. */
class PersonalCafeteriaController extends Controller
{
    use VerificaPermisos;

    public function __construct(private PersonalCafeteriaService $service)
    {
    }

    /** GET /api/cafeteria/personal?id_cafeteria=&estado=&id_user= */
    public function index(Request $request): JsonResponse
    {
        return $this->con($request, fn () => $this->service->listar($request->only('id_cafeteria', 'estado', 'id_user')));
    }

    /** GET /api/cafeteria/personal/candidatos?s= — personal activo que se puede asignar (máx. 20). */
    public function candidatos(Request $request): JsonResponse
    {
        return $this->con($request, fn () => $this->service->candidatos($request->input('s')));
    }

    /** POST /api/cafeteria/personal { id_cafeteria, id_user, id_rol_operativo, fecha_inicio, fecha_fin? } */
    public function store(AsignacionPersonalRequest $request): JsonResponse
    {
        return $this->con($request, fn () => $this->service->asignar($request->validated(), $request->user()->id_user));
    }

    /** PUT /api/cafeteria/personal/{asignacion}/finalizar { fecha_fin? } */
    public function finalizar(Request $request, int $asignacion): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::CAFETERIA_PERSONAL)) {
            return $rechazo;
        }

        $datos = $request->validate(
            ['fecha_fin' => ['nullable', 'date_format:Y-m-d']],
            ['fecha_fin.date_format' => 'La fecha de fin debe tener el formato AAAA-MM-DD'],
        );

        return $this->apiResponse($this->service->finalizar($asignacion, $datos['fecha_fin'] ?? null));
    }

    /** PUT /api/cafeteria/personal/{asignacion}/revocar */
    public function revocar(Request $request, int $asignacion): JsonResponse
    {
        return $this->con($request, fn () => $this->service->revocar($asignacion));
    }

    private function con(Request $request, callable $accion): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::CAFETERIA_PERSONAL)) {
            return $rechazo;
        }

        return $this->apiResponse($accion());
    }
}
