<?php

namespace App\Http\Controllers\FotosCurso;

use App\Enums\TipoPerfil;
use App\Http\Controllers\Controller;
use App\Services\FotosCurso\FotosCursoService;
use App\Services\Usuarios\PermisosPerfil;
use App\Support\Permiso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fotos del curso, lado del colegio. Publicar es autoservicio del docente (tipo DOCENTE) en los
 * cursos de su carga o que dirige, sin opción; la opción 174 ve y borra las de cualquier curso.
 * La ruta deja pasar a los dos (Permiso::ruta con tipo DOCENTE) y aquí se limita al curso.
 * Lo que ven los padres sale por PortalPadresController::semana.
 */
class FotosCursoController extends Controller
{
    public function __construct(private FotosCursoService $service)
    {
    }

    /** GET /api/fotos-curso/cursos */
    public function cursos(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->service->cursos($request->user()->id_user, $this->esModerador($request)));
    }

    /** GET /api/fotos-curso?id_curso=&pagina= (de a FotosCursoService::POR_PAGINA) */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['id_curso' => ['required', 'integer'], 'pagina' => ['nullable', 'integer', 'min:1']], ['id_curso.required' => 'Elige un curso']);

        if ($rechazo = $this->sinAcceso($request, (int) $data['id_curso'])) {
            return $rechazo;
        }

        return $this->apiResponse($this->service->listar((int) $data['id_curso'], $request->user()->id_user, $this->esModerador($request), (int) ($data['pagina'] ?? 1)));
    }

    /** GET /api/fotos-curso/opciones — sus asignaturas con los cursos donde las dicta, y «sin asignatura» con todos los suyos. */
    public function opciones(Request $request): JsonResponse
    {
        if (! $request->user()->esTipo(TipoPerfil::DOCENTE)) {
            return $this->error('Solo los docentes publican fotos', 403);
        }

        return $this->apiResponse($this->service->opciones($request->user()->id_user));
    }

    /** POST /api/fotos-curso (multipart) { id_asignatura?, id_cursos[], fecha, descripcion?, fotos[], confirma_autorizacion } */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_asignatura' => ['nullable', 'integer'],
            'id_cursos' => ['required', 'array', 'min:1', 'max:20'],
            'id_cursos.*' => ['integer', 'distinct'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'fotos' => ['required', 'array', 'min:1', 'max:' . FotosCursoService::MAX_FOTOS],
            'fotos.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'confirma_autorizacion' => ['accepted'],
        ], [
            'id_cursos.required' => 'Elige al menos un curso',
            'id_cursos.min' => 'Elige al menos un curso',
            'fecha.required' => 'Indica la fecha de la actividad',
            'fecha.date_format' => 'La fecha no es válida',
            'descripcion.max' => 'La descripción admite hasta 500 caracteres',
            'fotos.required' => 'Agrega al menos una foto',
            'fotos.min' => 'Agrega al menos una foto',
            'fotos.max' => 'Puedes publicar hasta ' . FotosCursoService::MAX_FOTOS . ' fotos a la vez',
            'fotos.*.uploaded' => 'Una de las fotos no se pudo subir: puede superar el tamaño permitido por el servidor',
            'fotos.*.image' => 'Solo se pueden publicar imágenes',
            'fotos.*.mimes' => 'Las fotos deben ser JPG, PNG o WEBP',
            'fotos.*.max' => 'Cada foto puede pesar hasta 10 MB',
            'confirma_autorizacion.accepted' => 'Confirma que en las fotos no aparecen estudiantes sin autorización de uso de imagen',
        ]);

        // Publicar: solo el docente, y solo en cursos donde dicta esa asignatura (o, sin asignatura,
        // en los suyos). El moderador modera, no publica en cursos ajenos.
        $idAsignatura = isset($data['id_asignatura']) ? (int) $data['id_asignatura'] : null;
        if (! $request->user()->esTipo(TipoPerfil::DOCENTE)
            || ! $this->service->puedePublicarEn($request->user()->id_user, $idAsignatura, $data['id_cursos'])) {
            return $this->error($idAsignatura ? 'Solo puedes publicar en los cursos donde dictas esa asignatura' : 'Solo puedes publicar en tus cursos', 403);
        }
        $data['id_asignatura'] = $idAsignatura;

        return $this->apiResponse($this->service->publicar($data, $request->file('fotos', []), $request->user()->id_user));
    }

    /** DELETE /api/fotos-curso/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->service->eliminar($id, $request->user()->id_user, $this->esModerador($request)));
    }

    private function esModerador(Request $request): bool
    {
        return PermisosPerfil::tiene($request, Permiso::FOTOS_CURSO_GESTIONAR);
    }

    /**
     * La misma regla del middleware de la ruta (opción 174 o tipo DOCENTE) y, con `$idCurso`,
     * que el docente sin la 174 sea del curso.
     */
    private function sinAcceso(Request $request, ?int $idCurso = null): ?JsonResponse
    {
        if ($this->esModerador($request)) {
            return null;
        }

        $usuario = $request->user();
        if (! $usuario->esTipo(TipoPerfil::DOCENTE)) {
            return $this->error('No tienes permiso para esta acción', 403);
        }

        if ($idCurso !== null && ! $this->service->esDocenteDelCurso($usuario->id_user, $idCurso)) {
            return $this->error('No eres docente de ese curso', 403);
        }

        return null;
    }
}
