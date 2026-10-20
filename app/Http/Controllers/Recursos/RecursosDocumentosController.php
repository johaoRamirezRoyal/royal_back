<?php

namespace App\Http\Controllers\Recursos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recursos\RenovacionDocumentoRequest;
use App\Services\recursos\RecursosDocumentosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\Permiso;
use App\Http\Controllers\Concerns\VerificaPermisos;

class RecursosDocumentosController extends Controller
{
    use VerificaPermisos;

    // 46 = Listado Maestro — Gestión (crear/editar/eliminar/ver cantidades). Ver el
    // documento y descargarlo/visualizarlo es autoservicio de cualquier usuario
    // autenticado, sin gate.
    private const OPCION_GESTION = Permiso::LISTADO_MAESTRO;

    public function __construct(
        private RecursosDocumentosServices $recursosServices,
    ) {
    }

    /**
     * GET /api/recursos/procesos — catálogo de tipos de proceso, sin gate (se usa en el
     * filtro del listado y en el formulario de creación, visibles para cualquier usuario).
     */
    public function procesos(): JsonResponse
    {
        return $this->apiResponse($this->recursosServices->procesos());
    }

    /**
     * GET /api/recursos/procesos/cantidades
     */
    public function cantidades(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION)) {
            return $rechazo;
        }

        return $this->apiResponse($this->recursosServices->procesosConCantidades());
    }

    /**
     * GET /api/recursos/documentos?per-page=&tipo_proceso=&s=
     */
    public function listar(Request $request): JsonResponse
    {
        $filtros = [
            'tipo_proceso' => $request->input('tipo_proceso'),
            's' => $request->input('s') ? trim($request->input('s')) : null,
        ];

        return $this->paginatedResponse(
            $this->recursosServices->listar($filtros, (int) $request->input('per-page', 15))
        );
    }

    /**
     * POST /api/recursos/documentos
     */
    public function crear(RenovacionDocumentoRequest $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION)) {
            return $rechazo;
        }

        return $this->apiResponse($this->recursosServices->crear(
            $request->validated(),
            $request->user()->id_user,
            $request->user()->id_user,
            $request->file('archivo')
        ));
    }

    /**
     * PUT|POST /api/recursos/documentos/{id} — PUT no parsea multipart en PHP, por eso el
     * frontend usa POST cuando hay archivo (mismo quirk que Proveedores/Solicitudes).
     */
    public function actualizar(RenovacionDocumentoRequest $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION)) {
            return $rechazo;
        }

        return $this->apiResponse($this->recursosServices->actualizar(
            $id,
            $request->validated(),
            $request->user()->id_user,
            $request->file('archivo')
        ));
    }

    /**
     * DELETE /api/recursos/documentos/{id} — baja lógica (activo=0).
     */
    public function eliminar(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION)) {
            return $rechazo;
        }

        return $this->apiResponse($this->recursosServices->eliminar($id, $request->user()->id_user));
    }
}
