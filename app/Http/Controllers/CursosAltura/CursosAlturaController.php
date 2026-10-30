<?php

namespace App\Http\Controllers\CursosAltura;

use App\Http\Controllers\Concerns\VerificaPermisos;
use App\Http\Controllers\Controller;
use App\Http\Requests\CursosAltura\CursoAlturaRequest;
use App\Services\CursosAltura\CursosAlturaServices;
use App\Support\Permiso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CursosAlturaController extends Controller
{
    use VerificaPermisos;

    // 143 = CRUD de cursos (Gestión Humana).
    private const OPCION_GESTION = Permiso::CURSOS_ALTURA;

    // Quienes reportan o programan mantenimientos de Áreas Comunes: consultan los
    // operativos vigentes y generan el permiso de alturas.
    private const OPCIONES_AREAS = [
        Permiso::INVENTARIO,
        Permiso::AREAS_COMUNES_ADMIN,
        Permiso::AREAS_COMUNES_USO,
        Permiso::MIS_AREAS_COMUNES,
        Permiso::MIS_AREAS_NIVEL,
        Permiso::MIS_AREAS_RESPONSABLE,
    ];

    public function __construct(
        private CursosAlturaServices $cursosAltura,
    ) {}

    /**
     * GET /api/cursos-altura?per-page=&s=
     */
    public function listar(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION)) {
            return $rechazo;
        }

        $s = $request->input('s') ? trim($request->input('s')) : null;

        return $this->paginatedResponse(
            $this->cursosAltura->listar(['s' => $s], (int) $request->input('per-page', 15))
        );
    }

    /**
     * GET /api/cursos-altura/operativos — operativos a los que se les puede registrar curso.
     */
    public function operativos(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION)) {
            return $rechazo;
        }

        return $this->apiResponse($this->cursosAltura->operativos());
    }

    /**
     * GET /api/cursos-altura/operativos-vigentes
     */
    public function operativosVigentes(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, ...self::OPCIONES_AREAS)) {
            return $rechazo;
        }

        return $this->apiResponse($this->cursosAltura->operativosVigentes());
    }

    /**
     * POST /api/cursos-altura
     */
    public function crear(CursoAlturaRequest $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION)) {
            return $rechazo;
        }

        return $this->apiResponse($this->cursosAltura->crear(
            $request->validated(),
            $request->user()->id_user,
            $request->file('certificado')
        ));
    }

    /**
     * POST /api/cursos-altura/{id} — POST y no PUT: PHP no parsea multipart en PUT.
     */
    public function actualizar(CursoAlturaRequest $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION)) {
            return $rechazo;
        }

        return $this->apiResponse($this->cursosAltura->actualizar(
            $id,
            $request->validated(),
            $request->user()->id_user,
            $request->file('certificado')
        ));
    }

    /**
     * DELETE /api/cursos-altura/{id} — baja lógica (activo=0).
     */
    public function eliminar(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION)) {
            return $rechazo;
        }

        return $this->apiResponse($this->cursosAltura->eliminar($id, $request->user()->id_user));
    }

    /**
     * GET /api/cursos-altura/permiso/{idReporte}/pdf — Permiso de Trabajo en Alturas.
     */
    public function permisoPdf(Request $request, int $idReporte): JsonResponse|Response
    {
        if ($rechazo = $this->sinAcceso($request, ...self::OPCIONES_AREAS)) {
            return $rechazo;
        }

        $resultado = $this->cursosAltura->permisoAlturasPdf($idReporte);

        if ($resultado['error']) {
            return $this->apiResponse($resultado);
        }

        return response($resultado['data']['contenido'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$resultado['data']['nombre_archivo'].'"',
        ]);
    }
}
