<?php

namespace App\Http\Controllers\AsistenciaGestion;

use App\Http\Controllers\Controller;
use App\Http\Requests\AsistenciaGestion\FiltroAsistenciaGestionRequest;
use App\Http\Requests\AsistenciaGestion\GraficaAsistenciaRequest;
use App\Services\AsistenciaTrabajadores\AsistenciaGestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\IpUtils;
use App\Support\Permiso;
use App\Services\Usuarios\PermisosPerfil;

class AsistenciaGestionController extends Controller
{
    // Opción de "/permisos" que habilita ver la asistencia de todos los usuarios,
    // no solo la propia (misma opción que ya gatea la ruta en el frontend).
    private const OPCION_VER_TODAS = Permiso::ASISTENCIA_TRABAJADORES;

    // Super Admin y Recursos humanos — únicos perfiles autorizados a revocar una llegada
    // tarde (a diferencia de OPCION_VER_TODAS, no es configurable desde "/permisos": el
    // alcance lo pidió explícitamente así, sin incluir Administrador).
    private const PERFILES_REVOCAR_LLEGADA_TARDE = [1, 8];

    private const PERFIL_COORDINADOR = Permiso::PERFIL_COORDINADOR;

    public function __construct(
        private AsistenciaGestionService $asistenciaService,
    ) {}

    /**
     * Sin el permiso de "ver todas", se ignora cualquier id_usuario solicitado por el
     * cliente y se fuerza el propio: la restricción de "llegadas de trabajadores" a
     * las asistencias propias debe cumplirse en el servidor, no solo ocultarse en la UI.
     */
    private function idUsuarioPermitido(Request $request, ?int $idUsuarioSolicitado): ?int
    {
        $usuarioAuth = $request->user();
        $puedeVerTodas = PermisosPerfil::tiene($request, self::OPCION_VER_TODAS);

        return $puedeVerTodas ? $idUsuarioSolicitado : $usuarioAuth->id_user;
    }

    /** QR de la pantalla de portería (ver AsistenciaGestionService::tokenQr). */
    public function tokenQr(Request $request): JsonResponse
    {
        if (!PermisosPerfil::tiene($request, self::OPCION_VER_TODAS)) {
            return $this->error('No tienes permiso para mostrar el código de asistencia', 403);
        }

        return $this->apiResponse(['error' => false, 'message' => 'OK', 'data' => $this->asistenciaService->tokenQr()]);
    }

    /**
     * El trabajador escanea el QR de portería con su celular. Quién marca sale de la sesión y
     * la hora del reloj del servidor; del cliente solo llega el token, que prueba que estuvo
     * frente a la pantalla en los últimos ~90 s. Con ASISTENCIA_QR_REDES (CIDRs separados por
     * coma) además tiene que venir desde la red del colegio.
     */
    public function marcarConQr(Request $request): JsonResponse
    {
        $token = (string) $request->input('token', '');
        if (!$this->asistenciaService->tokenQrValido($token)) {
            return $this->error('El código ya venció. Escanea de nuevo el de la pantalla.', 422);
        }

        $redes = array_filter(array_map('trim', explode(',', (string) config('services.asistencia_qr.redes'))));
        if ($redes && !IpUtils::checkIp((string) $request->ip(), $redes)) {
            return $this->error('Para marcar con el código tienes que estar conectado a la red del colegio.', 403);
        }

        $usuario = $request->user();
        if (in_array((int) $usuario->perfil, AsistenciaGestionService::PERFILES_EXCLUIDOS_ASISTENCIA, true)) {
            return $this->error('Tu perfil no registra asistencia de trabajadores.', 403);
        }

        // Mismo criterio que el debounce del webhook: un doble escaneo no puede leerse como la salida.
        if (!Cache::add('asistencia-qr:' . config('database.default') . ':' . $usuario->id_user, true, 60)) {
            return $this->error('Ya registramos tu marcación hace un momento.', 429);
        }

        $hora = now()->format('H:i:s');
        $resultado = $this->asistenciaService->registrarMarcacion(
            (int) $usuario->id_user,
            now()->toDateString(),
            $hora,
            AsistenciaGestionService::METODO_QR
        );
        $resultado['data'] = array_merge($resultado['data'] ?? [], ['hora_marcacion' => $hora]);

        return $this->apiResponse($resultado);
    }

    /**
     * Registro manual (a nombre de otro trabajador): solo quien puede ver/gestionar la
     * asistencia de todos (OPCION_VER_TODAS) — sin este chequeo, cualquier usuario podría
     * marcarle asistencia a cualquiera, o a sí mismo sin pasar por el dispositivo.
     */
    public function registrarAsistenciaManual(Request $request): JsonResponse
    {
        $usuarioAuth = $request->user();
        $puedeGestionar = PermisosPerfil::tiene($request, self::OPCION_VER_TODAS);

        if (!$puedeGestionar) {
            return $this->error('No tienes permiso para registrar asistencias manualmente', 403);
        }

        $validator = Validator::make($request->all(), [
            'id_user' => 'required|integer|exists:usuarios,id_user',
            'fecha_asistencia' => 'required|date|before_or_equal:today',
            'hora_asistencia' => 'required|date_format:H:i,H:i:s',
            'hora_salida' => 'nullable|date_format:H:i,H:i:s|after:hora_asistencia',
            'observacion' => 'nullable|string|max:1000',
        ], [
            'fecha_asistencia.before_or_equal' => 'La fecha no puede ser futura.',
            'hora_salida.after' => 'La hora de salida debe ser posterior a la de entrada.',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $nombre = trim(($usuarioAuth->nombre ?? '') . ' ' . ($usuarioAuth->apellido ?? ''));

        return $this->apiResponse($this->asistenciaService->registrarAsistenciaManual($validator->validated(), $nombre));
    }

    public function obtenerAsistencia(FiltroAsistenciaGestionRequest $request): JsonResponse
    {
        $filtros = $request->validated();

        // Coordinador: ve a todos los usuarios de SU nivel (asistencias y faltantes), y solo
        // ese nivel — se pisa cualquier id_nivel del cliente, incluso si tuviera OPCION_VER_TODAS.
        if ((int) $request->user()->perfil === self::PERFIL_COORDINADOR) {
            $filtros['id_nivel'] = $request->user()->id_nivel;
        } elseif ((int) $request->user()->perfil === AsistenciaGestionService::PERFIL_DIRECTOR_ADMINISTRATIVO) {
            // Director administrativo: solo los niveles que le corresponden; si el cliente
            // pide uno de ellos se respeta, cualquier otro se ignora.
            $niveles = AsistenciaGestionService::NIVELES_DIRECTOR_ADMINISTRATIVO;
            $filtros['id_nivel'] = in_array((int) ($filtros['id_nivel'] ?? 0), $niveles, true) ? (int) $filtros['id_nivel'] : $niveles;
        } else {
            $filtros['id_usuario'] = $this->idUsuarioPermitido($request, $filtros['id_usuario'] ?? null);
        }

        // El componente de tabla remota del frontend (DataTable) manda el tamaño de
        // página como "per-page" (guion) por convención genérica — FiltroAsistenciaGestionRequest
        // solo valida "per_page" (guion bajo), así que sin este alias el selector de
        // filas por página no tenía ningún efecto real (el service siempre caía al
        // default de 50). No pasa por el FormRequest porque no es un campo declarado ahí.
        if (!isset($filtros['per_page']) && $request->filled('per-page')) {
            $filtros['per_page'] = min(100, max(1, (int) $request->input('per-page')));
        }

        $resultado = $this->asistenciaService->obtenerAsistencia($filtros);

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
