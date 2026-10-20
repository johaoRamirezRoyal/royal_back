<?php

namespace App\Http\Controllers\AdminManagement;

use App\Http\Controllers\Controller;
use App\Services\AdminManagement\LogDominioService;
use Illuminate\Http\Request;
use App\Support\Permiso;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** Tráfico por dominio (toda petición, no solo escrituras — ver LogDominioMiddleware). Super Admin únicamente. */
class LogDominioController extends Controller implements HasMiddleware
{
    /** Solo Super Admin. Mismo permiso que su ruta: el controller no depende de que la ruta lo tenga. */
    public static function middleware(): array
    {
        return [new Middleware(Permiso::ruta([], [Permiso::PERFIL_SUPER_ADMIN]))];
    }

    public function __construct(
        private LogDominioService $service,
    ) {
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
