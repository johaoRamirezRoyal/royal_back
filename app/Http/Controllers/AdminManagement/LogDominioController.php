<?php

namespace App\Http\Controllers\AdminManagement;

use App\Http\Controllers\Controller;
use App\Services\AdminManagement\LogDominioService;
use Illuminate\Http\Request;

/** Tráfico por dominio (toda petición, no solo escrituras — ver LogDominioMiddleware). Super Admin únicamente. */
class LogDominioController extends Controller
{
    private const PERFILES_PERMITIDOS = [1];

    public function __construct(
        private LogDominioService $service,
        Request $request,
    ) {
        if (!in_array($request->user()->perfil, self::PERFILES_PERMITIDOS, true)) {
            abort($this->error('No tienes permiso para ver los logs por dominio', 403));
        }
    }

    public function index(Request $request)
    {
        $response = $this->service->listar(
            $request->only(['dominio', 'metodo', 'ruta', 'fecha_desde', 'fecha_hasta']),
            $request->integer('per-page') ?: null
        );

        return $this->paginatedResponse($response);
    }

    public function dominios(Request $request)
    {
        return $this->apiResponse($this->service->dominiosDisponibles());
    }
}
