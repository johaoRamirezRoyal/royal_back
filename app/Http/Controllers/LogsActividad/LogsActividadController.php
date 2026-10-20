<?php

namespace App\Http\Controllers\LogsActividad;

use App\Http\Controllers\Controller;
use App\Services\LogsActividad\LogsActividadService;
use Illuminate\Http\Request;
use App\Support\Permiso;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class LogsActividadController extends Controller implements HasMiddleware
{
    /** Solo Super Admin. Mismo permiso que su ruta: el controller no depende de que la ruta lo tenga. */
    public static function middleware(): array
    {
        return [new Middleware(Permiso::ruta([], [Permiso::PERFIL_SUPER_ADMIN]))];
    }

    protected $logs_service;

    public function __construct(LogsActividadService $logsService)
    {
        $this->logs_service = $logsService;
    }

    public function index(Request $request)
    {
        $response = $this->logs_service->listar(
            $request->only(['id_user', 'metodo', 'ruta', 'fecha_desde', 'fecha_hasta']),
            $request->integer('per-page') ?: null
        );

        return $this->paginatedResponse($response);
    }
}
