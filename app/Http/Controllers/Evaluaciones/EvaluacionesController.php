<?php

namespace App\Http\Controllers\Evaluaciones;

use App\Http\Controllers\Controller;
use App\Services\evaluaciones\EvaluacionesServices;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EvaluacionesController extends Controller
{
    // ponytail: IDs de permisos placeholder — ajustar al crear la opción en cron_opciones
    private const OPCION_VER = 102;
    private const OPCION_ADMIN = 101;
    private const OPCION_RESPONDER = 103;

    public function __construct(
        private EvaluacionesServices $evaluacionesServices,
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

    // ─── Catálogo de servicios ──────────────────────────────────

    public function listarServicios(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_VER, self::OPCION_ADMIN, self::OPCION_RESPONDER)) {
            return $rechazo;
        }

        return $this->apiResponse($this->evaluacionesServices->listarServicios());
    }

    public function crearServicio(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:150',
            'descripcion' => 'nullable|string',
            'activo' => 'sometimes|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        return $this->apiResponse($this->evaluacionesServices->crearServicio($validator->validated()));
    }

    public function actualizarServicio(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|max:150',
            'descripcion' => 'nullable|string',
            'activo' => 'sometimes|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        return $this->apiResponse($this->evaluacionesServices->actualizarServicio($id, $validator->validated()));
    }

    public function eliminarServicio(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        return $this->apiResponse($this->evaluacionesServices->eliminarServicio($id));
    }

    // ─── Tipos de pregunta ─────────────────────────────────────

    public function listarTiposPregunta(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_VER, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        return $this->apiResponse($this->evaluacionesServices->listarTiposPregunta());
    }

    // ─── Evaluaciones ──────────────────────────────────────────

    public function listar(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_VER, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        return $this->paginatedResponse(
            $this->evaluacionesServices->listar($request->only([
                'id_servicio', 'activo', 's', 'per-page',
            ]))
        );
    }

    public function obtenerPorId(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_VER, self::OPCION_ADMIN, self::OPCION_RESPONDER)) {
            return $rechazo;
        }

        return $this->apiResponse($this->evaluacionesServices->obtenerPorId($id));
    }

    public function crear(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'id_servicio' => 'required|integer|exists:evaluaciones_servicios,id',
            'activo' => 'sometimes|integer',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'niveles' => 'sometimes|array',
            'niveles.*' => 'integer|exists:nivel,id',
            'perfiles' => 'sometimes|array',
            'perfiles.*' => 'integer|exists:perfiles,id_perfil',
            'secciones' => 'sometimes|array',
            'secciones.*.titulo' => 'required_with:secciones|string|max:255',
            'secciones.*.descripcion' => 'nullable|string',
            'secciones.*.porcentaje' => 'required_with:secciones|numeric|min:0|max:100',
            'secciones.*.orden' => 'nullable|integer',
            'secciones.*.preguntas' => 'sometimes|array',
            'secciones.*.preguntas.*.id_tipo_pregunta' => 'required_with:secciones.*.preguntas|integer|exists:evaluaciones_tipos_pregunta,id',
            'secciones.*.preguntas.*.texto' => 'required_with:secciones.*.preguntas|string|max:500',
            'secciones.*.preguntas.*.obligatoria' => 'sometimes|integer',
            'secciones.*.preguntas.*.permite_comentario' => 'sometimes|integer',
            'secciones.*.preguntas.*.opciones' => 'sometimes|array',
            'secciones.*.preguntas.*.opciones.*.texto' => 'required_with:secciones.*.preguntas.*.opciones|string|max:255',
            'secciones.*.preguntas.*.opciones.*.valor' => 'required_with:secciones.*.preguntas.*.opciones|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        $datos = $validator->validated();
        $datos['id_user'] = $request->user()->id_user;

        return $this->apiResponse($this->evaluacionesServices->crear($datos));
    }

    public function actualizar(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'titulo' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string',
            'id_servicio' => 'sometimes|integer|exists:evaluaciones_servicios,id',
            'activo' => 'sometimes|integer',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'niveles' => 'sometimes|array',
            'niveles.*' => 'integer|exists:nivel,id',
            'perfiles' => 'sometimes|array',
            'perfiles.*' => 'integer|exists:perfiles,id_perfil',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        return $this->apiResponse($this->evaluacionesServices->actualizar($id, $validator->validated()));
    }

    public function eliminar(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        return $this->apiResponse($this->evaluacionesServices->eliminar($id));
    }

    public function toggleActivo(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        return $this->apiResponse($this->evaluacionesServices->toggleActivo($id));
    }

    public function obtenerEvaluables(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        return $this->apiResponse($this->evaluacionesServices->obtenerEvaluables($id, $request->user()));
    }

    // ─── Secciones ─────────────────────────────────────────────

    public function crearSeccion(Request $request, int $idEvaluacion): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'porcentaje' => 'required|numeric|min:0|max:100',
            'orden' => 'nullable|integer',
            'activo' => 'sometimes|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        return $this->apiResponse($this->evaluacionesServices->crearSeccion($idEvaluacion, $validator->validated()));
    }

    public function actualizarSeccion(Request $request, int $idSeccion): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'titulo' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string',
            'porcentaje' => 'sometimes|numeric|min:0|max:100',
            'orden' => 'nullable|integer',
            'activo' => 'sometimes|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        return $this->apiResponse($this->evaluacionesServices->actualizarSeccion($idSeccion, $validator->validated()));
    }

    public function eliminarSeccion(Request $request, int $idSeccion): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        return $this->apiResponse($this->evaluacionesServices->eliminarSeccion($idSeccion));
    }

    // ─── Preguntas ─────────────────────────────────────────────

    public function crearPregunta(Request $request, int $idSeccion): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'id_tipo_pregunta' => 'required|integer|exists:evaluaciones_tipos_pregunta,id',
            'texto' => 'required|string|max:500',
            'obligatoria' => 'sometimes|integer',
            'permite_comentario' => 'sometimes|integer',
            'orden' => 'nullable|integer',
            'opciones' => 'sometimes|array',
            'opciones.*.texto' => 'required_with:opciones|string|max:255',
            'opciones.*.valor' => 'required_with:opciones|numeric',
            'opciones.*.orden' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        return $this->apiResponse($this->evaluacionesServices->crearPregunta($idSeccion, $validator->validated()));
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
            'permite_comentario' => 'sometimes|integer',
            'orden' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        return $this->apiResponse($this->evaluacionesServices->actualizarPregunta($idPregunta, $validator->validated()));
    }

    public function eliminarPregunta(Request $request, int $idPregunta): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        return $this->apiResponse($this->evaluacionesServices->eliminarPregunta($idPregunta));
    }

    // ─── Opciones ──────────────────────────────────────────────

    public function crearOpcion(Request $request, int $idPregunta): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'texto' => 'required|string|max:255',
            'valor' => 'required|numeric',
            'orden' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        return $this->apiResponse($this->evaluacionesServices->crearOpcion($idPregunta, $validator->validated()));
    }

    public function actualizarOpcion(Request $request, int $idOpcion): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'texto' => 'sometimes|string|max:255',
            'valor' => 'sometimes|numeric',
            'orden' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        return $this->apiResponse($this->evaluacionesServices->actualizarOpcion($idOpcion, $validator->validated()));
    }

    public function eliminarOpcion(Request $request, int $idOpcion): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        return $this->apiResponse($this->evaluacionesServices->eliminarOpcion($idOpcion));
    }

    // ─── Respuestas ────────────────────────────────────────────

    public function enviarRespuesta(Request $request, int $idEvaluacion): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_RESPONDER, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'anonima' => 'sometimes|integer',
            'id_evaluado' => 'nullable|integer|exists:usuarios,id_user',
            'id_nivel' => 'nullable|integer|exists:nivel,id',
            'respuestas' => 'required|array|min:1',
            'respuestas.*.id_pregunta' => 'required|integer|exists:evaluaciones_preguntas,id',
            'respuestas.*.id_opcion' => 'nullable|integer|exists:evaluaciones_opciones_pregunta,id',
            'respuestas.*.valor_texto' => 'nullable|string',
            'respuestas.*.comentario' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        return $this->apiResponse(
            $this->evaluacionesServices->enviarRespuesta(
                $idEvaluacion,
                $request->user()->id_user,
                $validator->validated()
            )
        );
    }

    public function listarRespuestas(Request $request, int $idEvaluacion): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_VER, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        return $this->paginatedResponse(
            $this->evaluacionesServices->listarRespuestas($idEvaluacion, $request->only(['anonima', 'per-page']))
        );
    }

    public function obtenerRespuesta(Request $request, int $idRespuesta): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_VER, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        return $this->apiResponse($this->evaluacionesServices->obtenerRespuesta($idRespuesta));
    }

    // ─── Resultados ────────────────────────────────────────────

    public function calcularResultados(Request $request, int $idEvaluacion): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_VER, self::OPCION_ADMIN)) {
            return $rechazo;
        }

        return $this->apiResponse($this->evaluacionesServices->calcularResultados($idEvaluacion));
    }
}
