<?php

namespace App\Http\Controllers\AsistenciaGestion;

use App\Http\Controllers\Controller;
use App\Http\Requests\AsistenciaGestion\FiltroAsistenciaGestionRequest;
use App\Http\Requests\AsistenciaGestion\GraficaAsistenciaRequest;
use App\Http\Requests\AsistenciaGestion\StoreAsistenciaGestionRequest;
use App\Services\AsistenciaTrabajadores\AsistenciaGestionService;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AsistenciaGestionController extends Controller
{
    // Opción de "/permisos" que habilita ver la asistencia de todos los usuarios,
    // no solo la propia (misma opción que ya gatea la ruta en el frontend).
    private const OPCION_VER_TODAS = 63;

    // Super Admin y Recursos humanos — únicos perfiles autorizados a revocar una llegada
    // tarde (a diferencia de OPCION_VER_TODAS, no es configurable desde "/permisos": el
    // alcance lo pidió explícitamente así, sin incluir Administrador).
    private const PERFILES_REVOCAR_LLEGADA_TARDE = [1, 8];

    public function __construct(
        private AsistenciaGestionService $asistenciaService,
        private UsuariosServices $usuariosService,
    ) {}

    /**
     * Sin el permiso de "ver todas", se ignora cualquier id_usuario solicitado por el
     * cliente y se fuerza el propio: la restricción de "llegadas de trabajadores" a
     * las asistencias propias debe cumplirse en el servidor, no solo ocultarse en la UI.
     */
    private function idUsuarioPermitido(Request $request, ?int $idUsuarioSolicitado): ?int
    {
        $usuarioAuth = $request->user();
        $puedeVerTodas = $this->usuariosService->tienePermiso(self::OPCION_VER_TODAS, $usuarioAuth->perfil)['permiso'] ?? false;

        return $puedeVerTodas ? $idUsuarioSolicitado : $usuarioAuth->id_user;
    }

    public function registrarAsistencia(StoreAsistenciaGestionRequest $request): JsonResponse
    {
        $data = $request->validated();

        $resultado = $this->asistenciaService->registrarAsistencia(
            $data['id_user'],
            $data['fecha_asistencia'],
            $data['hora_asistencia']
        );

        return $this->apiResponse($resultado);
    }

    public function obtenerAsistencia(FiltroAsistenciaGestionRequest $request): JsonResponse
    {
        $filtros = $request->validated();
        $filtros['id_usuario'] = $this->idUsuarioPermitido($request, $filtros['id_usuario'] ?? null);

        $resultado = $this->asistenciaService->obtenerAsistencia($filtros);

        return $this->apiResponse($resultado);
    }

    public function obtenerResumenPorUsuario(FiltroAsistenciaGestionRequest $request): JsonResponse
    {
        $resultado = $this->asistenciaService->obtenerResumenPorUsuario($request->validated());

        return $this->apiResponse($resultado);
    }

    public function obtenerDatosGrafica(FiltroAsistenciaGestionRequest $request): JsonResponse
    {
        $resultado = $this->asistenciaService->obtenerDatosGrafica($request->validated());

        return $this->apiResponse($resultado);
    }

    public function topUsuariosLlegadasTarde(GraficaAsistenciaRequest $request): JsonResponse
    {
        $resultado = $this->asistenciaService->topUsuariosLlegadasTarde($request->validated());

        return $this->apiResponse($resultado);
    }

    public function distribucionHorasLlegada(GraficaAsistenciaRequest $request): JsonResponse
    {
        $resultado = $this->asistenciaService->distribucionHorasLlegada($request->validated());

        return $this->apiResponse($resultado);
    }

    public function promedioHoraLlegadaPorUsuario(GraficaAsistenciaRequest $request): JsonResponse
    {
        $resultado = $this->asistenciaService->promedioHoraLlegadaPorUsuario($request->validated());

        return $this->apiResponse($resultado);
    }

    public function ultimosRegistrosUsuario(Request $request): JsonResponse
    {
        $request->validate([
            'id_usuario' => ['required', 'integer', 'exists:usuarios,id_user'],
            'limite' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [
            'id_usuario.required' => 'El ID del usuario es obligatorio.',
            'id_usuario.integer' => 'El ID del usuario debe ser numérico.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            'limite.integer' => 'El límite debe ser numérico.',
            'limite.min' => 'El límite debe ser al menos 1.',
            'limite.max' => 'El límite no puede ser mayor a 100.',
        ]);

        $resultado = $this->asistenciaService->ultimosRegistrosUsuario(
            $request->input('id_usuario'),
            $request->input('limite', 30)
        );

        return $this->apiResponse($resultado);
    }

    public function eliminarAsistencia(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:asistencia_gestion,id'],
        ], [
            'ids.required' => 'Debe proporcionar al menos un ID.',
            'ids.array' => 'Los IDs deben ser un arreglo.',
            'ids.min' => 'Debe proporcionar al menos un ID.',
            'ids.*.integer' => 'Cada ID debe ser numérico.',
            'ids.*.exists' => 'Uno o más IDs no existen en la base de datos.',
        ]);

        $resultado = $this->asistenciaService->eliminarAsistencia($request->input('ids'));

        return $this->apiResponse($resultado);
    }

    public function actualizarObservacion(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'observacion' => ['nullable', 'string', 'max:255'],
        ], [
            'observacion.string' => 'La observación debe ser texto.',
            'observacion.max' => 'La observación no puede superar los 255 caracteres.',
        ]);

        $resultado = $this->asistenciaService->actualizarObservacion($id, $request->input('observacion'));

        return $this->apiResponse($resultado);
    }

    public function revocarLlegadaTarde(Request $request, int $id): JsonResponse
    {
        if (!in_array((int) $request->user()->perfil, self::PERFILES_REVOCAR_LLEGADA_TARDE, true)) {
            return $this->error('No tienes permiso para revocar llegadas tarde', 403);
        }

        $request->validate([
            'observacion' => ['nullable', 'string', 'max:255'],
        ], [
            'observacion.string' => 'La observación debe ser texto.',
            'observacion.max' => 'La observación no puede superar los 255 caracteres.',
        ]);

        $resultado = $this->asistenciaService->revocarLlegadaTarde($id, $request->input('observacion'));

        return $this->apiResponse($resultado);
    }
}
