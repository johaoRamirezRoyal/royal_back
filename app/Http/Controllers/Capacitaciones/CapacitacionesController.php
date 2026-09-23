<?php

namespace App\Http\Controllers\Capacitaciones;

use App\Http\Controllers\Controller;
use App\Pdf\Capacitaciones\CertificadoCapacitacionPdfService;
use App\Services\capacitaciones\CapacitacionesServices;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CapacitacionesController extends Controller
{
    // 85 = ver y realizar capacitaciones, 86 = administrarlas,
    // 93 = listado de capacitaciones realizadas (certificados de otros usuarios).
    private const OPCION_VER = 85;
    private const OPCION_ADMIN = 86;
    private const OPCION_REALIZADAS = 93;

    public function __construct(
        private CapacitacionesServices $capacitacionesServices,
        private UsuariosServices $usuariosService,
    ) {
    }

    private function tiene(Request $request, int $opcion): bool
    {
        return $this->usuariosService->tienePermiso($opcion, $request->user()->perfil)['permiso'] ?? false;
    }

    private function sinAcceso(Request $request, int ...$opciones): ?JsonResponse
    {
        foreach ($opciones as $opcion) {
            if ($this->tiene($request, $opcion)) {
                return null;
            }
        }

        return $this->error('No tienes permiso para esta acción', 403);
    }

    // ─── Realizar capacitaciones (85; 86 puede previsualizar) ───

    /** GET /api/capacitaciones */
    public function disponibles(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_VER)) {
            return $rechazo;
        }

        return $this->apiResponse($this->capacitacionesServices->cursosDisponibles($request->user()));
    }

    /** GET /api/capacitaciones/{id} */
    public function detalle(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_VER, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        return $this->apiResponse($this->capacitacionesServices->detalleCurso(
            $id, $request->user(), $this->tiene($request, self::OPCION_ADMIN)
        ));
    }

    /** POST /api/capacitaciones/contenidos/{id}/visto */
    public function marcarVisto(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_VER, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        return $this->apiResponse($this->capacitacionesServices->marcarVisto(
            $id, $request->user(), $this->tiene($request, self::OPCION_ADMIN)
        ));
    }

    /** GET /api/capacitaciones/{id}/quiz */
    public function quiz(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_VER, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        return $this->apiResponse($this->capacitacionesServices->preguntasQuiz(
            $id, $request->user(), $this->tiene($request, self::OPCION_ADMIN)
        ));
    }

    /** POST /api/capacitaciones/{id}/quiz — body: { respuestas: { id_pregunta: id_opcion } } */
    public function responderQuiz(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_VER, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        $data = $request->validate([
            'respuestas' => ['required', 'array'],
            'respuestas.*' => ['required', 'integer'],
        ], [
            'respuestas.required' => 'Debes responder la prueba',
        ]);

        return $this->apiResponse($this->capacitacionesServices->responderQuiz(
            $id, $data['respuestas'], $request->user(), $this->tiene($request, self::OPCION_ADMIN)
        ));
    }

    /** GET /api/capacitaciones/mis-completadas — autoservicio (pestaña de /profile). */
    public function misCompletadas(Request $request): JsonResponse
    {
        return $this->apiResponse($this->capacitacionesServices->completadasDeUsuario($request->user()->id_user));
    }

    /**
     * GET /api/capacitaciones/{id}/certificado[?id_user=]
     * El certificado propio no exige opción (se ve también desde /profile); el de otro
     * usuario exige la 93. En ambos casos la capacitación debe estar completada.
     */
    public function certificado(Request $request, int $id, CertificadoCapacitacionPdfService $pdfService): Response
    {
        $idUser = (int) $request->input('id_user', $request->user()->id_user);

        if ($idUser !== (int) $request->user()->id_user && ($rechazo = $this->sinAcceso($request, self::OPCION_REALIZADAS))) {
            return $rechazo;
        }

        $resultado = $this->capacitacionesServices->datosCertificado($id, $idUser);

        if ($resultado['error']) {
            return response()->json(['error' => true, 'message' => $resultado['message']], $resultado['status'] ?? 422);
        }

        ['curso' => $curso, 'usuario' => $usuario, 'fecha' => $fecha] = $resultado['data'];

        // Código estable por (usuario, curso) — el legado generaba un uniqid() distinto en cada descarga.
        $codigo = strtoupper(substr(hash_hmac('sha256', "{$idUser}|{$id}", config('app.key')), 0, 12));

        $contenido = $pdfService->generate(
            // Hay usuarios legacy con apellido "." o "," — el legado ya los limpiaba.
            trim(preg_replace('/\s+/', ' ', $usuario->nombre . ' ' . $usuario->apellido), " ,."),
            trim($curso->nombre),
            trim($curso->descripcion),
            substr($fecha, 0, 10),
            $codigo
        );

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="certificado-' . Str::slug($curso->nombre) . '.pdf"',
        ]);
    }

    // ─── Capacitaciones realizadas (93) ─────────────────────────

    /** GET /api/capacitaciones/realizadas?per-page=&s= */
    public function realizadas(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_REALIZADAS)) {
            return $rechazo;
        }

        return $this->paginatedResponse($this->capacitacionesServices->usuariosConCapacitaciones(
            ['s' => $request->input('s') ? trim($request->input('s')) : null],
            (int) $request->input('per-page', 15)
        ));
    }

    /** GET /api/capacitaciones/realizadas/{idUser} */
    public function realizadasDeUsuario(Request $request, int $idUser): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_REALIZADAS)) {
            return $rechazo;
        }

        return $this->apiResponse($this->capacitacionesServices->completadasDeUsuario($idUser));
    }

    // ─── Administración (86) ────────────────────────────────────

    /** GET /api/capacitaciones/admin/cursos?per-page=&s=&activo= */
    public function listarCursos(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        return $this->paginatedResponse($this->capacitacionesServices->listarCursosAdmin(
            ['s' => $request->input('s') ? trim($request->input('s')) : null, 'activo' => $request->input('activo')],
            (int) $request->input('per-page', 15)
        ));
    }

    /** POST /api/capacitaciones/admin/cursos y POST /admin/cursos/{id} con _method=PUT (multipart) */
    public function guardarCurso(Request $request, ?int $id = null): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:355'],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'nombre.required' => 'El nombre es obligatorio',
            'imagen.image' => 'La imagen debe ser un archivo de imagen',
        ]);

        return $this->apiResponse($this->capacitacionesServices->guardarCurso(
            $id, $data, $request->file('imagen'), $request->user()->id_user
        ));
    }

    /** PATCH /api/capacitaciones/admin/cursos/{id}/estado — body: { activo: bool } */
    public function cambiarEstado(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        $data = $request->validate(['activo' => ['required', 'boolean']]);

        return $this->apiResponse($this->capacitacionesServices->cambiarEstadoCurso($id, $data['activo']));
    }

    /** GET /api/capacitaciones/admin/cursos/{id}/modulos — módulos con sus contenidos. */
    public function modulos(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        return $this->apiResponse($this->capacitacionesServices->modulosAdmin($id));
    }

    /** POST /api/capacitaciones/admin/cursos/{id}/modulos */
    public function crearModulo(Request $request, int $id): JsonResponse
    {
        return $this->guardarModulo($request, null, $id);
    }

    /** PUT /api/capacitaciones/admin/modulos/{id} */
    public function actualizarModulo(Request $request, int $id): JsonResponse
    {
        return $this->guardarModulo($request, $id, null);
    }

    private function guardarModulo(Request $request, ?int $idModulo, ?int $idCurso): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:350'],
        ], ['nombre.required' => 'El nombre es obligatorio']);

        return $this->apiResponse($this->capacitacionesServices->guardarModulo(
            $idModulo, $data + ['id_curso' => $idCurso], $request->user()->id_user
        ));
    }

    /** DELETE /api/capacitaciones/admin/modulos/{id} */
    public function eliminarModulo(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        return $this->apiResponse($this->capacitacionesServices->eliminarModulo($id));
    }

    /** POST /api/capacitaciones/admin/modulos/{id}/contenidos */
    public function crearContenido(Request $request, int $id): JsonResponse
    {
        return $this->guardarContenido($request, null, $id);
    }

    /** PUT /api/capacitaciones/admin/contenidos/{id} */
    public function actualizarContenido(Request $request, int $id): JsonResponse
    {
        return $this->guardarContenido($request, $id, null);
    }

    private function guardarContenido(Request $request, ?int $idContenido, ?int $idModulo): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:100'],
            'contenido' => ['required', 'url', 'max:350'],
        ], [
            'nombre.required' => 'El nombre es obligatorio',
            'contenido.required' => 'La URL del contenido es obligatoria',
            'contenido.url' => 'El contenido debe ser una URL válida',
        ]);

        return $this->apiResponse($this->capacitacionesServices->guardarContenido(
            $idContenido, $data + ['id_modulo' => $idModulo], $request->user()->id_user
        ));
    }

    /** DELETE /api/capacitaciones/admin/contenidos/{id} */
    public function eliminarContenido(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        return $this->apiResponse($this->capacitacionesServices->eliminarContenido($id));
    }

    /** GET /api/capacitaciones/admin/cursos/{id}/quiz — incluye `esCorrecto`. */
    public function quizAdmin(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        return $this->apiResponse($this->capacitacionesServices->quizAdmin($id));
    }

    /** PUT /api/capacitaciones/admin/cursos/{id}/quiz — reemplaza la prueba completa. */
    public function guardarQuiz(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        $data = $request->validate([
            'preguntas' => ['present', 'array'],
            'preguntas.*.enunciado' => ['required', 'string', 'max:400'],
            'preguntas.*.opciones' => ['required', 'array', 'min:2'],
            'preguntas.*.opciones.*.enunciado' => ['required', 'string', 'max:300'],
            'preguntas.*.opciones.*.esCorrecto' => ['required', 'boolean'],
        ], [
            'preguntas.*.enunciado.required' => 'Todas las preguntas deben tener enunciado',
            'preguntas.*.opciones.min' => 'Cada pregunta debe tener al menos dos opciones',
            'preguntas.*.opciones.*.enunciado.required' => 'Todas las opciones deben tener texto',
        ]);

        foreach ($data['preguntas'] as $i => $pregunta) {
            if (!collect($pregunta['opciones'])->contains(fn ($o) => (bool) $o['esCorrecto'])) {
                return $this->error('La pregunta ' . ($i + 1) . ' no tiene una opción correcta', 422);
            }
        }

        return $this->apiResponse($this->capacitacionesServices->guardarQuiz($id, $data['preguntas'], $request->user()->id_user));
    }

    /** GET /api/capacitaciones/admin/cursos/{id}/perfiles — ids de perfiles autorizados. */
    public function perfiles(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        return $this->apiResponse($this->capacitacionesServices->perfilesCurso($id));
    }

    /** PUT /api/capacitaciones/admin/cursos/{id}/perfiles — body: { perfiles: int[] } */
    public function guardarPerfiles(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        $data = $request->validate([
            'perfiles' => ['present', 'array'],
            'perfiles.*' => ['integer', 'exists:perfiles,id_perfil'],
        ]);

        return $this->apiResponse($this->capacitacionesServices->sincronizarPerfiles($id, $data['perfiles'], $request->user()->id_user));
    }
}
