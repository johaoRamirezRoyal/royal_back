<?php

namespace App\Http\Controllers\GestionAcademica;

use App\Http\Controllers\Controller;
use App\Models\GestionAcademica\ActaEstudiante;
use App\Services\GestionAcademica\ActasEstudianteService;
use App\Services\GestionAcademica\CargaAcademicaService;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Registro de actas de estudiantes. Lectura (listado, conteo, gráficas): opción 136 o 137
 * (todas), u opción 138 (solo las de los estudiantes de los cursos del docente).
 * Escritura (crear/editar/eliminar): opción 137. Ver ActasEstudianteService.
 */
class ActasEstudianteController extends Controller
{
    private const OPCION_VER = 136;

    private const OPCION_GESTIONAR = 137;

    private const OPCION_MIS_ESTUDIANTES = 138;

    public function __construct(
        private ActasEstudianteService $service,
        private UsuariosServices $usuariosService,
        private CargaAcademicaService $cargaAcademica,
    ) {}

    private function tiene(Request $request, int $opcion): bool
    {
        return (bool) ($this->usuariosService->tienePermiso($opcion, $request->user()->perfil)['permiso'] ?? false);
    }

    /**
     * Lectura: con 136/137 ve todas; con solo la 138, limita el servicio a los estudiantes de
     * los cursos donde el usuario tiene carga académica activa más las actas que él solicitó.
     * Sin ninguna, rechaza.
     */
    private function sinLectura(Request $request): ?JsonResponse
    {
        if ($this->tiene($request, self::OPCION_VER) || $this->tiene($request, self::OPCION_GESTIONAR)) {
            $this->service->limitarACursos(null);

            return null;
        }

        if ($this->tiene($request, self::OPCION_MIS_ESTUDIANTES)) {
            $cursos = $this->cargaAcademica->obtenerCursosDocente($request->user()->id_user)['data'] ?? [];
            $this->service->limitarACursos(collect($cursos)->pluck('id')->all(), $request->user()->id_user);

            return null;
        }

        return $this->error('No tienes permiso para esta acción', 403);
    }

    private function sinAcceso(Request $request, int ...$opciones): ?JsonResponse
    {
        $perfil = $request->user()->perfil;

        foreach ($opciones as $opcion) {
            if ($this->usuariosService->tienePermiso($opcion, $perfil)['permiso'] ?? false) {
                return null;
            }
        }

        return $this->error('No tienes permiso para esta acción', 403);
    }

    private const FILTROS = ['id_anio_escolar', 'id_periodo_academico', 'id_nivel', 'id_curso', 'id_estudiante', 'tipo', 'id_docente_solicita', 'asociacion'];

    /** Listado con el contrato de DataTable remoto (page, per-page, s, sort, dir) + FILTROS; `agrupar=1` = una fila por estudiante. */
    public function index(Request $request)
    {
        if ($rechazo = $this->sinLectura($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->service->listar(
            filtros: $request->only(self::FILTROS),
            pagina: (int) $request->input('page', 1),
            porPagina: (int) $request->input('per-page', $request->input('per_page', 20)),
            busqueda: $request->input('s'),
            orden: $request->input('sort'),
            direccion: $request->input('dir'),
            agrupar: $request->boolean('agrupar'),
        ));
    }

    public function filtros(Request $request)
    {
        if ($rechazo = $this->sinLectura($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->service->filtros());
    }

    public function estadisticas(Request $request)
    {
        if ($rechazo = $this->sinLectura($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->service->estadisticas($request->only(self::FILTROS)));
    }

    /** ?id_estudiante=&fecha=Y-m-d[&excluir_id=] — actas de diálogo formativo en ese periodo. */
    public function conteo(Request $request)
    {
        if ($rechazo = $this->sinLectura($request)) {
            return $rechazo;
        }

        $datos = $request->validate([
            'id_estudiante' => 'required|integer',
            'fecha' => 'required|date_format:Y-m-d',
            'excluir_id' => 'nullable|integer',
        ]);

        return $this->apiResponse($this->service->conteo(
            (int) $datos['id_estudiante'],
            $datos['fecha'],
            isset($datos['excluir_id']) ? (int) $datos['excluir_id'] : null,
        ));
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'id_estudiante' => 'required|integer|exists:usuarios,id_user',
            'fecha' => 'required|date_format:Y-m-d',
            'tipo' => ['required', Rule::in(ActaEstudiante::TIPOS)],
            'asunto' => 'required|string|max:200',
            'observacion' => 'nullable|string|max:2000',
            'id_docente_solicita' => 'nullable|integer',
        ], [
            'asunto.required' => 'Escribe el asunto del acta.',
        ]);
    }

    /** Body: { "actas": [ { id_estudiante, fecha, tipo, asunto, observacion, id_docente_solicita? }, ... ] } — hasta 50, todo o nada. */
    public function store(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTIONAR)) {
            return $rechazo;
        }

        $datos = $request->validate([
            'actas' => 'required|array|min:1|max:50',
            'actas.*.id_estudiante' => 'required|integer|exists:usuarios,id_user',
            'actas.*.fecha' => 'required|date_format:Y-m-d',
            'actas.*.tipo' => ['required', Rule::in(ActaEstudiante::TIPOS)],
            'actas.*.asunto' => 'required|string|max:200',
            'actas.*.observacion' => 'nullable|string|max:2000',
            'actas.*.id_docente_solicita' => 'nullable|integer',
        ], [
            'actas.*.asunto.required' => 'Escribe el asunto de cada acta.',
        ]);

        return $this->apiResponse($this->service->crear($datos['actas'], $request->user()->id_user));
    }

    public function update(Request $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTIONAR)) {
            return $rechazo;
        }

        return $this->apiResponse($this->service->actualizar($id, $this->validar($request)));
    }

    public function destroy(Request $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTIONAR)) {
            return $rechazo;
        }

        return $this->apiResponse($this->service->eliminar($id));
    }
}
