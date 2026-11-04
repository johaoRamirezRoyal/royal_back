<?php

namespace App\Http\Controllers\Cursos;

use App\Http\Controllers\Concerns\VerificaPermisos;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cursos\CrearEstudianteRequest;
use App\Http\Requests\Cursos\CursoRequest;
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
            [CursosServices::PERFIL_ESTUDIANTE],
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
