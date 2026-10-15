<?php

namespace App\Http\Controllers\GestionAcademica;

use App\Http\Controllers\Controller;
use App\Models\GestionAcademica\ActaEstudiante;
use App\Services\GestionAcademica\ActasEstudianteService;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Registro de actas de estudiantes. Lectura (listado, conteo, gráficas): opción 136 o 137.
 * Escritura (crear/editar/eliminar): opción 137. Ver ActasEstudianteService.
 */
class ActasEstudianteController extends Controller
{
    private const OPCION_VER = 136;

    private const OPCION_GESTIONAR = 137;

    public function __construct(
        private ActasEstudianteService $service,
        private UsuariosServices $usuariosService,
    ) {}

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

    private const FILTROS = ['id_anio_escolar', 'id_periodo_academico', 'id_nivel', 'id_curso', 'id_estudiante', 'tipo'];

    /** Listado con el contrato de DataTable remoto (page, per-page, s, sort, dir) + FILTROS. */
    public function index(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_VER, self::OPCION_GESTIONAR)) {
            return $rechazo;
        }

        return $this->apiResponse($this->service->listar(
            filtros: $request->only(self::FILTROS),
            pagina: (int) $request->input('page', 1),
            porPagina: (int) $request->input('per-page', $request->input('per_page', 20)),
            busqueda: $request->input('s'),
            orden: $request->input('sort'),
            direccion: $request->input('dir'),
        ));
    }

    public function filtros(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_VER, self::OPCION_GESTIONAR)) {
            return $rechazo;
        }

        return $this->apiResponse($this->service->filtros());
    }

    public function estadisticas(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_VER, self::OPCION_GESTIONAR)) {
            return $rechazo;
        }

        return $this->apiResponse($this->service->estadisticas($request->only(self::FILTROS)));
    }

    /** ?id_estudiante=&fecha=Y-m-d[&excluir_id=] — actas de diálogo formativo en ese periodo. */
    public function conteo(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_VER, self::OPCION_GESTIONAR)) {
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
            'observacion' => 'nullable|string|max:2000',
        ]);
    }

    /** Body: { "actas": [ { id_estudiante, fecha, tipo, observacion }, ... ] } — hasta 50, todo o nada. */
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
            'actas.*.observacion' => 'nullable|string|max:2000',
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
