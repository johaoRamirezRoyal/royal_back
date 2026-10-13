<?php

namespace App\Http\Controllers\GestionAcademica;

use App\Http\Controllers\Controller;
use App\Services\GestionAcademica\ConsultaAsistenciaClaseService;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\Request;

/**
 * Consulta de asistencias de clases de todos los docentes, de solo lectura.
 *
 * Controller aparte de GestionAcademicaController a propósito: el constructor de aquel
 * bloquea todo a quien no tenga la opción 99 (o sea docente en sus métodos propios), y
 * esta consulta tiene que poder darse a perfiles que NO deben gestionar lo académico
 * (coordinación, rectoría, secretaría...). Acepta la opción propia (132) o la 99 completa.
 */
class ConsultaAsistenciaClaseController extends Controller
{
    private const OPCION_CONSULTA_ASISTENCIAS = 132;

    private const OPCION_GESTION_ACADEMICA = 99;

    public function __construct(
        private ConsultaAsistenciaClaseService $service,
        UsuariosServices $usuariosService,
        Request $request,
    ) {
        $perfil = $request->user()->perfil;

        foreach ([self::OPCION_CONSULTA_ASISTENCIAS, self::OPCION_GESTION_ACADEMICA] as $opcion) {
            if ($usuariosService->tienePermiso($opcion, $perfil)['permiso'] ?? false) {
                return;
            }
        }

        abort($this->error('No tienes permiso para consultar las asistencias de clases', 403));
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
