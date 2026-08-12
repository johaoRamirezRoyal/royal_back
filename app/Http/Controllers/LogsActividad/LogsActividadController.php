<?php

namespace App\Http\Controllers\LogsActividad;

use App\Http\Controllers\Controller;
use App\Services\LogsActividad\LogsActividadService;
use Illuminate\Http\Request;

class LogsActividadController extends Controller
{
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
