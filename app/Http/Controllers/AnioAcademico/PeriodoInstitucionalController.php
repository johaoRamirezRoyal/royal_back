<?php

namespace App\Http\Controllers\AnioAcademico;

use App\Http\Controllers\Controller;
use App\Services\AnioEscolar\PeriodoServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lectura pública (cualquier autenticado, sin gate de opción) del catálogo de `periodos`
 * (año/periodo institucional, con `en_curso`) — mismo criterio que
 * AnioAcademico::obtenerAniosAcademicos: varios módulos (Inventario, Mantenimiento,
 * Evaluaciones) necesitan saber "cuál es el periodo en curso" solo para precargar un
 * default, y no tiene sentido atarlos a la opción 107 de administración de
 * Año Escolar y Periodos, ni duplicar esta consulta en cada uno.
 */
class PeriodoInstitucionalController extends Controller
{
    public function __construct(protected PeriodoServices $periodoServices)
    {
    }

    public function listar(Request $request): JsonResponse
    {
        return response()->json($this->periodoServices->listar($request->only(['activo', 'id_anio'])));
    }

    public function enCurso(): JsonResponse
    {
        return response()->json($this->periodoServices->periodoActivo());
    }
}
