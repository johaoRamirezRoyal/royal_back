<?php

namespace App\Http\Controllers\GestionAcademica;

use App\Http\Controllers\Controller;
use App\Services\GestionAcademica\ConsultaAsistenciaClaseService;
use Illuminate\Http\Request;
use App\Support\Permiso;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Consulta de asistencias de clases de todos los docentes, de solo lectura.
 *
 * Controller aparte de GestionAcademicaController a propósito: el constructor de aquel
 * bloquea todo a quien no tenga la opción 99 (o sea docente en sus métodos propios), y
 * esta consulta tiene que poder darse a perfiles que NO deben gestionar lo académico
 * (coordinación, rectoría, secretaría...). Acepta la opción propia (132) o la 99 completa.
 */
class ConsultaAsistenciaClaseController extends Controller implements HasMiddleware
{
    /** Opción propia o Gestión Académica completa. Mismo permiso que su ruta: el controller no depende de que la ruta lo tenga. */
    public static function middleware(): array
    {
        return [new Middleware(Permiso::ruta([Permiso::CONSULTA_ASISTENCIAS_CLASES, Permiso::GESTION_ACADEMICA]))];
    }

    public function __construct(
        private ConsultaAsistenciaClaseService $service,
    ) {
    }

    /**
     * Listado paginado con el contrato de DataTable remoto (page, per-page, s, sort, dir) +
     * filtros fecha_inicio, fecha_fin, id_curso, id_docente, id_asignatura, estado.
     */
    public function index(Request $request)
    {
        return $this->apiResponse($this->service->listar(
            filtros: $request->only(['fecha_inicio', 'fecha_fin', 'id_curso', 'id_docente', 'id_asignatura', 'estado']),
            pagina: (int) $request->input('page', 1),
            porPagina: (int) $request->input('per-page', $request->input('per_page', 20)),
            busqueda: $request->input('s'),
            orden: $request->input('sort'),
            direccion: $request->input('dir'),
        ));
    }

    /** Registro por estudiante de una clase (los presentes se derivan: no tienen fila). */
    public function show(int $id)
    {
        return $this->apiResponse($this->service->detalle($id));
    }

    public function filtros()
    {
        return $this->apiResponse($this->service->filtros());
    }
}
