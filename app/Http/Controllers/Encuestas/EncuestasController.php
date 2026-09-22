<?php

namespace App\Http\Controllers\Encuestas;

use App\Http\Controllers\Controller;
use App\Services\encuestas\EncuestasServices;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EncuestasController extends Controller
{
    // La migración 2026_09_22_090600_seed_opciones_encuestas insertó estas opciones con
    // ids 126/127 en la BD local (insertGetId, mismo drift documentado para Instituciones/
    // Noticias/Evaluaciones). En producción las mismas opciones ya existían con ids 129/130
    // — 2026_09_22_100000_fix_ids_opciones_encuestas renumera la BD local para que
    // coincida, así que estas constantes reflejan el id real en ambos entornos.
    private const OPCION_ADMIN = 129;
    private const OPCION_VER = 130;

    public function __construct(
        private EncuestasServices $encuestasServices,
        private UsuariosServices $usuariosService,
    ) {}

    private function sinAcceso(Request $request, int ...$opciones): ?JsonResponse
    {
        $perfil = $request->user()->perfil;

        if ($this->usuariosService->tieneAlgunPermiso($opciones, $perfil)['permiso'] ?? false) {
            return null;
        }

        return $this->error('No tienes permiso para esta acción', 403);
    }

    // ─── Catálogo de tipos de pregunta (reusa el de Evaluaciones) ────

    public function listarTiposPregunta(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN, self::OPCION_VER)) {
            return $rechazo;
        }

        return $this->apiResponse($this->encuestasServices->listarTiposPregunta());
    }

    // ─── Encuestas ────────────────────────────────────────────────

    public function listar(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN, self::OPCION_VER)) {
            return $rechazo;
        }

        return $this->paginatedResponse(
            $this->encuestasServices->listar($request->only(['s', 'activo', 'per-page']))
        );
    }

    public function obtenerPorId(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN, self::OPCION_VER)) {
            return $rechazo;
        }

        return $this->apiResponse($this->encuestasServices->obtenerPorId($id));
    }

    public function crear(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'activo' => 'sometimes|integer',
            'preguntas' => 'sometimes|array',
            'preguntas.*.id_tipo_pregunta' => 'required_with:preguntas|integer|exists:evaluaciones_tipos_pregunta,id',
            'preguntas.*.texto' => 'required_with:preguntas|string|max:500',
            'preguntas.*.obligatoria' => 'sometimes|integer',
            'preguntas.*.orden' => 'nullable|integer',
            'preguntas.*.opciones' => 'sometimes|array',
            'preguntas.*.opciones.*.texto' => 'required_with:preguntas.*.opciones|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        return $this->apiResponse($this->encuestasServices->crear($validator->validated()));
    }

    public function actualizar(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'titulo' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string',
            'activo' => 'sometimes|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        return $this->apiResponse($this->encuestasServices->actualizar($id, $validator->validated()));
    }

    public function eliminar(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        return $this->apiResponse($this->encuestasServices->eliminar($id));
    }

    public function toggleActivo(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        return $this->apiResponse($this->encuestasServices->toggleActivo($id));
    }

    // ─── Preguntas ────────────────────────────────────────────────

    public function crearPregunta(Request $request, int $idEncuesta): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'id_tipo_pregunta' => 'required|integer|exists:evaluaciones_tipos_pregunta,id',
            'texto' => 'required|string|max:500',
            'obligatoria' => 'sometimes|integer',
            'orden' => 'nullable|integer',
            'opciones' => 'sometimes|array',
            'opciones.*.texto' => 'required_with:opciones|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        return $this->apiResponse($this->encuestasServices->crearPregunta($idEncuesta, $validator->validated()));
    }

    public function actualizarPregunta(Request $request, int $idPregunta): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'id_tipo_pregunta' => 'sometimes|integer|exists:evaluaciones_tipos_pregunta,id',
            'texto' => 'sometimes|string|max:500',
            'obligatoria' => 'sometimes|integer',
            'orden' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        return $this->apiResponse($this->encuestasServices->actualizarPregunta($idPregunta, $validator->validated()));
    }

    public function eliminarPregunta(Request $request, int $idPregunta): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        return $this->apiResponse($this->encuestasServices->eliminarPregunta($idPregunta));
    }

    // ─── Opciones de pregunta ─────────────────────────────────────

    public function crearOpcion(Request $request, int $idPregunta): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'texto' => 'required|string|max:255',
            'orden' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        return $this->apiResponse($this->encuestasServices->crearOpcion($idPregunta, $validator->validated()));
    }

    public function actualizarOpcion(Request $request, int $idOpcion): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'texto' => 'sometimes|string|max:255',
            'orden' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        return $this->apiResponse($this->encuestasServices->actualizarOpcion($idOpcion, $validator->validated()));
    }

    public function eliminarOpcion(Request $request, int $idOpcion): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        return $this->apiResponse($this->encuestasServices->eliminarOpcion($idOpcion));
    }

    // ─── Salones ──────────────────────────────────────────────────

    public function listarSalones(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN, self::OPCION_VER)) {
            return $rechazo;
        }

        return $this->apiResponse($this->encuestasServices->listarSalones());
    }

    public function asignarEncuestaSalon(Request $request, int $idSalon): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'id_encuesta' => 'nullable|integer|exists:encuestas,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        return $this->apiResponse($this->encuestasServices->asignarEncuestaSalon($idSalon, $validator->validated()['id_encuesta'] ?? null));
    }

    /** Guarda de una sola vez la encuesta activa de varios salones — botón "Guardar cambios" de la pestaña Salones y QR. */
    public function asignarEncuestasLote(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'asignaciones' => 'required|array|min:1',
            'asignaciones.*.id_salon' => 'required|integer',
            'asignaciones.*.id_encuesta' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        return $this->apiResponse($this->encuestasServices->asignarEncuestasLote($validator->validated()['asignaciones']));
    }

    public function regenerarTokenSalon(Request $request, int $idSalon): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        return $this->apiResponse($this->encuestasServices->regenerarTokenSalon($idSalon));
    }

    // ─── Resultados ───────────────────────────────────────────────

    public function resultados(Request $request, int $idEncuesta): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN, self::OPCION_VER)) {
            return $rechazo;
        }

        $idSalon = $request->query('id_salon');
        $idReserva = $request->query('id_reserva');
        return $this->apiResponse($this->encuestasServices->resultados(
            $idEncuesta,
            $idSalon !== null ? (int) $idSalon : null,
            $idReserva !== null ? (int) $idReserva : null,
        ));
    }

    public function reservasConRespuestas(Request $request, int $idEncuesta): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN, self::OPCION_VER)) {
            return $rechazo;
        }

        $idSalon = $request->query('id_salon');
        return $this->apiResponse($this->encuestasServices->reservasConRespuestas($idEncuesta, $idSalon !== null ? (int) $idSalon : null));
    }

    public function salonesConRespuestas(Request $request, int $idEncuesta): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN, self::OPCION_VER)) {
            return $rechazo;
        }

        return $this->apiResponse($this->encuestasServices->salonesConRespuestas($idEncuesta));
    }

    // ─── Flujo público (sin auth, anónimo — ver routes/api.php) ──────

    public function obtenerEncuestaPublica(Request $request, string $token): JsonResponse
    {
        return $this->apiResponse($this->encuestasServices->obtenerEncuestaPublica($token));
    }

    public function reservasHoySalon(Request $request, string $token): JsonResponse
    {
        return $this->apiResponse($this->encuestasServices->reservasHoySalon($token));
    }

    public function responderPublica(Request $request, string $token): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_reserva' => 'nullable|integer|exists:reservas,id',
            'respuestas' => 'sometimes|array',
            'respuestas.*.id_pregunta' => 'required_with:respuestas|integer',
            'respuestas.*.id_opcion' => 'nullable|integer',
            'respuestas.*.valor_texto' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        $datos = $validator->validated();
        return $this->apiResponse($this->encuestasServices->responderPublica($token, $datos['respuestas'] ?? [], $datos['id_reserva'] ?? null));
    }
}
