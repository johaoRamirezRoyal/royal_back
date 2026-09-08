<?php

namespace App\Http\Controllers\Tramites;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tramites\StoreTramiteRequest;
use App\Services\tramites\TramitesServices;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TramitesController extends Controller
{
    // 77 = Listado de trámites + aprobar/rechazar/subir documento. Solicitar un trámite
    // y ver "mis trámites" es autoservicio de cualquier usuario, sin gate.
    private const OPCION_GESTION = 77;

    public function __construct(
        private TramitesServices $tramitesServices,
        private UsuariosServices $usuariosService,
    ) {
    }

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

    /** GET /api/tramites/tipos — catálogo, sin gate (necesario para el formulario de solicitud). */
    public function tipos(): JsonResponse
    {
        return $this->apiResponse($this->tramitesServices->tipos());
    }

    /** GET /api/tramites/grupos-familiares */
    public function gruposFamiliares(): JsonResponse
    {
        return $this->apiResponse($this->tramitesServices->gruposFamiliares());
    }

    /** GET /api/tramites/eps */
    public function eps(): JsonResponse
    {
        return $this->apiResponse($this->tramitesServices->epsCatalogo());
    }

    /** GET /api/tramites/mis-tramites */
    public function misTramites(Request $request): JsonResponse
    {
        return $this->apiResponse($this->tramitesServices->misTramites($request->user()->id_user));
    }

    /** POST /api/tramites */
    public function crear(StoreTramiteRequest $request): JsonResponse
    {
        return $this->apiResponse($this->tramitesServices->crear(
            $request->validated(),
            $request->file('archivos') ?? [],
            (array) $request->input('grupo_familiar_ids', []),
            $request->user(),
            $request->user()->id_user
        ));
    }

    /** GET /api/tramites?per-page=&id_user=&tipo_tramite=&estado=&s= */
    public function listar(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION)) {
            return $rechazo;
        }

        $filtros = [
            'id_user' => $request->input('id_user'),
            'tipo_tramite' => $request->input('tipo_tramite'),
            'estado' => $request->input('estado'),
            's' => $request->input('s') ? trim($request->input('s')) : null,
        ];

        return $this->paginatedResponse($this->tramitesServices->listar($filtros, (int) $request->input('per-page', 15)));
    }

    /** GET /api/tramites/{id} */
    public function detalle(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION)) {
            return $rechazo;
        }

        return $this->apiResponse($this->tramitesServices->detalle($id));
    }

    /** POST /api/tramites/{id}/finalizar — multipart opcional (archivo). */
    public function finalizar(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION)) {
            return $rechazo;
        }

        $request->validate([
            'archivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:10240'],
        ]);

        return $this->apiResponse($this->tramitesServices->finalizar($id, $request->user()->id_user, $request->file('archivo')));
    }

    /** POST /api/tramites/{id}/rechazar */
    public function rechazar(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION)) {
            return $rechazo;
        }

        $request->validate(['motivo' => ['required', 'string', 'max:2000']]);

        return $this->apiResponse($this->tramitesServices->rechazar($id, $request->user()->id_user, $request->input('motivo')));
    }
}
