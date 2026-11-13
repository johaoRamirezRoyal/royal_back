<?php

namespace App\Http\Controllers\Cursos;

use App\Enums\TipoPerfil;
use App\Models\Usuarios\Perfil;
use App\Http\Controllers\Concerns\VerificaPermisos;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cursos\CrearEstudianteRequest;
use App\Http\Requests\Cursos\CursoRequest;
use App\Services\Carnets\CarnetService;
use App\Services\Cursos\CursosServices;
use App\Services\Usuarios\UsuariosServices;
use App\Support\Permiso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CursosController extends Controller
{
    use VerificaPermisos;

    // 145 = administrar cursos y sus estudiantes. /all queda abierto (selects de otros módulos).
    private const OPCION_CURSOS = Permiso::CURSOS;

    public function __construct(
        private CursosServices $cursos,
        private UsuariosServices $usuarios,
    ) {}

    public function findAll()
    {
        return $this->success('Se han encontrado cursos', $this->cursos->getCursos());
    }

    /** GET /api/cursos/grados — catálogo de grados: lo leen Cursos (145) y Configuración académica (99). */
    public function grados(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_CURSOS, Permiso::GESTION_ACADEMICA)) {
            return $rechazo;
        }

        return $this->apiResponse($this->cursos->listarGrados());
    }

    /** POST /api/cursos/grados y PUT /api/cursos/grados/{id}: solo Configuración académica (99). */
    public function guardarGrado(Request $request, ?int $id = null): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::GESTION_ACADEMICA)) {
            return $rechazo;
        }
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:80'],
            'id_nivel_academico' => ['nullable', 'integer', 'exists:nivel_academico,id'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'activo' => ['nullable', 'boolean'],
        ], ['nombre.required' => 'El nombre del grado es obligatorio']);

        return $this->apiResponse($this->cursos->guardarGrado($data, $id));
    }

    /** GET /api/cursos?s=&estado=activo|inactivo&per-page= */
    public function listar(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_CURSOS)) {
            return $rechazo;
        }

        return $this->paginatedResponse($this->cursos->listar(
            $request->input('s') ? trim($request->input('s')) : null,
            $request->input('estado'),
            (int) $request->input('per-page', 15),
        ));
    }

    /**
     * GET /api/cursos/mi-curso — autoservicio del director de grupo (perfil docente): su curso y
     * sus estudiantes con quién sugirió que repita. data null si no dirige ninguno.
     */
    public function miCurso(Request $request): JsonResponse
    {
        if (! $request->user()->esTipo(TipoPerfil::DOCENTE)) {
            return $this->error('No tienes permiso para esta acción', 403);
        }

        return $this->apiResponse($this->cursos->miCurso($request->user()->id_user));
    }

    /**
     * PUT /api/cursos/mi-curso/repiten { estudiantes: [id_user, ...] } — reemplaza la lista de
     * los que el director sugiere que repitan. Solo sugerencia: promover sigue siendo de la 145.
     */
    public function sugerirRepiten(Request $request): JsonResponse
    {
        if (! $request->user()->esTipo(TipoPerfil::DOCENTE)) {
            return $this->error('No tienes permiso para esta acción', 403);
        }

        $data = $request->validate([
            'estudiantes' => ['present', 'array', 'max:200'],
            'estudiantes.*' => ['integer'],
        ]);

        return $this->apiResponse($this->cursos->sugerirRepiten($request->user()->id_user, $data['estudiantes']));
    }

    /**
     * PUT /api/cursos/mi-curso/carnets/entregar { ids: [id_user, ...] } — opción 146: el director
     * marca entregados los carnets por entregar de SU curso (los demás ids se ignoran). La
     * entrega y su movimiento los hace CarnetService, igual que en Carnets (144).
     */
    public function entregarCarnetsMiCurso(Request $request, CarnetService $carnets): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::CARNETS_MI_CURSO)) {
            return $rechazo;
        }

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
        ]);

        $ids = $this->cursos->estudiantesDeMiCurso($request->user()->id_user, $data['ids']);

        if (! $ids) {
            return $this->error('Ninguno de esos estudiantes está en el curso que diriges', 422);
        }

        return $this->apiResponse($carnets->entregar($ids, $request->user()->id_user));
    }

    /** GET /api/cursos/docentes — para elegir director de grupo. */
    public function docentes(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_CURSOS)) {
            return $rechazo;
        }

        return $this->apiResponse($this->cursos->docentes());
    }

    /** GET /api/cursos/{id} */
    public function detalle(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_CURSOS)) {
            return $rechazo;
        }

        return $this->apiResponse($this->cursos->detalle($id));
    }

    /** POST /api/cursos */
    public function crear(CursoRequest $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_CURSOS)) {
            return $rechazo;
        }

        return $this->apiResponse($this->cursos->crear($request->validated(), $request->user()->id_user));
    }

    /** PUT /api/cursos/{id} */
    public function actualizar(CursoRequest $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_CURSOS)) {
            return $rechazo;
        }

        return $this->apiResponse($this->cursos->actualizar($id, $request->validated(), $request->user()->id_user));
    }

    /**
     * GET /api/cursos/{id}/estudiantes?s=&per-page= — estudiantes activos del curso.
     * GET /api/cursos/estudiantes-buscar?s=&per-page= — todos los estudiantes activos (para asignar).
     */
    public function estudiantes(Request $request, ?int $id = null): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_CURSOS)) {
            return $rechazo;
        }

        return $this->paginatedResponse($this->usuarios->mostrarUsuariosPaginados(
            (int) $request->input('per-page', 10),
            Perfil::idsDeTipo(TipoPerfil::ESTUDIANTE),
            null,
            $request->input('s'),
            'activo',
            'nombre',
            'asc',
            $id ? [$id] : null,
        ));
    }

    /** POST /api/cursos/{id}/estudiantes { estudiantes: [id_user, ...] } — mueve si ya tenían curso. */
    public function asignarEstudiantes(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_CURSOS)) {
            return $rechazo;
        }

        $data = $request->validate([
            'estudiantes' => ['required', 'array', 'min:1', 'max:200'],
            'estudiantes.*' => ['integer'],
        ], [
            'estudiantes.required' => 'Selecciona al menos un estudiante',
        ]);

        return $this->apiResponse($this->cursos->asignarEstudiantes($id, $data['estudiantes']));
    }

    /** POST /api/cursos/{id}/estudiantes/nuevo — crea el usuario Estudiante ya en el curso. */
    public function crearEstudiante(CrearEstudianteRequest $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_CURSOS)) {
            return $rechazo;
        }

        $data = $request->toUsuarioFormatCreate();
        $data['fechareg'] = now();
        $data['user_log'] = $request->user()->id_user;

        $response = $this->usuarios->agregarUsuario($data);

        return $this->apiResponse($response['error']
            ? $response
            : ['error' => false, 'message' => 'Estudiante creado en el curso', 'data' => $response['usuario']]);
    }

    /**
     * GET /api/cursos/avisos-cierre — avisos de la página de inicio al terminar el año: falta
     * promover (lo atiende la 145) o definir los periodos del año siguiente (la 99).
     */
    public function avisosCierreAnio(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_CURSOS, Permiso::GESTION_ACADEMICA)) {
            return $rechazo;
        }

        return $this->apiResponse($this->cursos->avisosCierreAnio());
    }

    /** GET /api/cursos/promocion — vista previa: a dónde pasa cada curso. */
    public function previaPromocion(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_CURSOS)) {
            return $rechazo;
        }

        return $this->apiResponse($this->cursos->previaPromocion());
    }

    /** POST /api/cursos/promocion { cursos: [id, ...] } — pasa sus estudiantes al curso próximo. */
    public function promover(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_CURSOS)) {
            return $rechazo;
        }

        $data = $request->validate([
            'cursos' => ['required', 'array', 'min:1'],
            'cursos.*' => ['integer'],
            'excluir' => ['sometimes', 'array'],
            'excluir.*' => ['integer'],
        ], [
            'cursos.required' => 'Selecciona al menos un curso',
        ]);

        return $this->apiResponse($this->cursos->promover($data['cursos'], $data['excluir'] ?? [], $request->user()->id_user));
    }

    /** GET /api/cursos/promocion/estudiantes?cursos[]= — los estudiantes que se promoverían. */
    public function estudiantesParaPromocion(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_CURSOS)) {
            return $rechazo;
        }

        $data = $request->validate([
            'cursos' => ['required', 'array', 'min:1'],
            'cursos.*' => ['integer'],
        ]);

        return $this->apiResponse($this->cursos->estudiantesParaPromocion($data['cursos']));
    }

    /** DELETE /api/cursos/{id}/estudiantes/{idEstudiante} — lo deja sin curso. */
    public function quitarEstudiante(Request $request, int $id, int $idEstudiante): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_CURSOS)) {
            return $rechazo;
        }

        return $this->apiResponse($this->cursos->quitarEstudiante($id, $idEstudiante));
    }
}
