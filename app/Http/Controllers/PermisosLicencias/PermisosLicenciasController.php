<?php

namespace App\Http\Controllers\PermisosLicencias;

use App\Http\Controllers\Controller;
use App\Http\Requests\PermisosLicencias\ActualizarPermisoRequest;
use App\Http\Requests\PermisosLicencias\StorePermisoRequest;
use App\Services\permisosLicencias\PermisosLicenciasServices;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermisosLicenciasController extends Controller
{
    private const OPCION_ACCESO = 80;
    private const OPCION_LISTADO = 81;
    private const OPCION_EDITAR_PROPIA = 82;
    private const OPCION_GESTION = 83;
    private const OPCION_DETALLE = 90;
    private const OPCION_ASIGNAR = 92;

    /** Coordinador/Directivo: ve (y le notifican) solo las solicitudes de su propio nivel. */
    private const PERFILES_COORDINACION_NIVEL = [26, 7];

    public function __construct(
        private PermisosLicenciasServices $service,
        private UsuariosServices $usuariosService,
    ) {
    }

    private function tienePermiso(Request $request, int $opcion): bool
    {
        return $this->usuariosService->tienePermiso($opcion, $request->user()->perfil)['permiso'] ?? false;
    }

    private function sinAcceso(Request $request, int ...$opciones): ?JsonResponse
    {
        foreach ($opciones as $opcion) {
            if ($this->tienePermiso($request, $opcion)) {
                return null;
            }
        }

        return $this->error('No tienes permiso para esta acción', 403);
    }

    // ── Catálogos (sin gate, necesarios en el formulario) ──────────────

    public function tipos(): JsonResponse
    {
        return $this->apiResponse($this->service->tipos());
    }

    public function motivos(): JsonResponse
    {
        return $this->apiResponse($this->service->motivos());
    }

    public function catalogoLey(): JsonResponse
    {
        return $this->apiResponse($this->service->catalogoLey());
    }

    public function catalogoPersonal(): JsonResponse
    {
        return $this->apiResponse($this->service->catalogoPersonal());
    }

    public function catalogoInstitucional(): JsonResponse
    {
        return $this->apiResponse($this->service->catalogoInstitucional());
    }

    /** GET /usuarios-asignables — opción 92 (asignar el permiso a otro usuario). */
    public function usuariosAsignables(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ASIGNAR)) {
            return $rechazo;
        }

        return $this->apiResponse($this->service->usuariosAsignables());
    }

    // ── Autoservicio ────────────────────────────────────────────────

    /** GET /mis-solicitudes — opción 80 (módulo habilitado). */
    public function misSolicitudes(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ACCESO)) {
            return $rechazo;
        }

        return $this->apiResponse($this->service->misSolicitudes($request->user()->id_user));
    }

    /**
     * POST / — opción 80. Si trae `id_user_asignado` y el actor tiene la opción 92, el
     * permiso se registra a nombre de ese usuario (perfiles 10/23/27/32) en vez del propio.
     */
    public function crear(StorePermisoRequest $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ACCESO)) {
            return $rechazo;
        }

        $idAsignado = $request->input('id_user_asignado');
        $idBeneficiario = ($idAsignado && $this->tienePermiso($request, self::OPCION_ASIGNAR))
            ? (int) $idAsignado
            : $request->user()->id_user;

        return $this->apiResponse($this->service->crear(
            $request->validated(),
            $idBeneficiario,
            $request->user()->id_user,
            $request->file('evidencia_permiso')
        ));
    }

    /**
     * PUT|POST /{id} — opción 82 (propia, solo pendiente) u opción 83 (de cualquiera).
     */
    public function actualizar(ActualizarPermisoRequest $request, int $id): JsonResponse
    {
        $puedeEditarTodas = $this->tienePermiso($request, self::OPCION_GESTION);

        if (!$puedeEditarTodas && $rechazo = $this->sinAcceso($request, self::OPCION_EDITAR_PROPIA)) {
            return $rechazo;
        }

        return $this->apiResponse($this->service->actualizar(
            $id,
            $request->validated(),
            $request->file('evidencia_permiso'),
            $request->user()->id_user,
            $puedeEditarTodas,
            $request->user()->id_user
        ));
    }

    // ── Gestión ──────────────────────────────────────────────────────

    /** GET /?per-page=&id_user=&tipo_permiso=&motivo_permiso=&estado=&fecha_desde=&fecha_hasta=&s= — opción 81. */
    public function listar(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_LISTADO)) {
            return $rechazo;
        }

        $idNivelViewer = in_array($request->user()->perfil, self::PERFILES_COORDINACION_NIVEL, true)
            ? $request->user()->id_nivel
            : null;

        $filtros = [
            'id_user' => $request->input('id_user'),
            'tipo_permiso' => $request->input('tipo_permiso'),
            'motivo_permiso' => $request->input('motivo_permiso'),
            'estado' => $request->input('estado'),
            'fecha_desde' => $request->input('fecha_desde'),
            'fecha_hasta' => $request->input('fecha_hasta'),
            's' => $request->input('s') ? trim($request->input('s')) : null,
        ];

        return $this->paginatedResponse($this->service->listar($filtros, $idNivelViewer, (int) $request->input('per-page', 15)));
    }

    /** GET /{id} — opción 90. */
    public function detalle(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_DETALLE)) {
            return $rechazo;
        }

        return $this->apiResponse($this->service->detalle($id));
    }

    /** POST /{id}/aprobar — opción 83. */
    public function aprobar(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION)) {
            return $rechazo;
        }

        $request->validate(['remunerado' => ['required', 'in:si,no']]);

        return $this->apiResponse($this->service->aprobar($id, $request->user()->id_user, $request->input('remunerado')));
    }

    /** POST /{id}/rechazar — opción 83. */
    public function rechazar(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION)) {
            return $rechazo;
        }

        $request->validate(['motivo' => ['required', 'string', 'max:2000']]);

        return $this->apiResponse($this->service->rechazar($id, $request->user()->id_user, $request->input('motivo')));
    }
}
