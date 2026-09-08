<?php

namespace App\Http\Controllers\Certificados;

use App\Http\Controllers\Controller;
use App\Http\Requests\Certificados\StoreSolicitudCertificadoRequest;
use App\Services\certificados\CertificadosServices;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificadosController extends Controller
{
    // 25 = Listado de Certificados Solicitados (Gestión Humana). Solicitar un
    // certificado y ver "mis solicitudes" es autoservicio de cualquier usuario
    // autenticado, sin gate — por eso no hay un chequeo único en el constructor.
    private const OPCION_LISTADO = 25;

    public function __construct(
        private CertificadosServices $certificadosServices,
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

    /**
     * GET /api/certificados/mis-solicitudes
     */
    public function misSolicitudes(Request $request): JsonResponse
    {
        return $this->apiResponse($this->certificadosServices->misSolicitudes($request->user()->id_user));
    }

    /**
     * POST /api/certificados
     */
    public function crear(StoreSolicitudCertificadoRequest $request): JsonResponse
    {
        return $this->apiResponse($this->certificadosServices->crear($request->validated(), $request->user()));
    }

    /**
     * GET /api/certificados?per-page=&estado=&tipo_cert=&s=
     */
    public function listar(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_LISTADO)) {
            return $rechazo;
        }

        $filtros = [
            'estado' => $request->input('estado'),
            'tipo_cert' => $request->input('tipo_cert'),
            's' => $request->input('s') ? trim($request->input('s')) : null,
        ];

        return $this->paginatedResponse(
            $this->certificadosServices->listar($filtros, (int) $request->input('per-page', 15))
        );
    }

    /**
     * POST /api/certificados/{id}/documento — sube (o reemplaza) el archivo final.
     */
    public function subirDocumento(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_LISTADO)) {
            return $rechazo;
        }

        $request->validate([
            'archivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        return $this->apiResponse($this->certificadosServices->subirDocumento(
            $id,
            $request->file('archivo'),
            $request->user()->id_user
        ));
    }
}
