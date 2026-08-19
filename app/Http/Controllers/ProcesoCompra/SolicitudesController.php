<?php

namespace App\Http\Controllers\ProcesoCompra;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcesoCompra\AplazarSolicitudRequest;
use App\Http\Requests\ProcesoCompra\AsignarProveedorRequest;
use App\Http\Requests\ProcesoCompra\RechazarSolicitudRequest;
use App\Http\Requests\ProcesoCompra\SolicitudInicialRequest;
use App\Http\Requests\ProcesoCompra\VerificarEntregaRequest;
use App\Http\Requests\ProcesoCompra\VerificarSolicitudRequest;
use App\Services\ProcesoCompra\SolicitudesServices;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SolicitudesController extends Controller
{
    // Opción "Listado de solicitudes" (60) del módulo "Proceso de compra" (9).
    // Gatea el listado/detalle; la creación la hace cualquier empleado autenticado.
    private const OPCION_SOLICITUDES = 60;

    public function __construct(
        private SolicitudesServices $solicitudesServices,
        private UsuariosServices $usuariosService,
    ) {}

    private function sinAcceso(Request $request): ?JsonResponse
    {
        $tienePermiso = $this->usuariosService->tienePermiso(self::OPCION_SOLICITUDES, $request->user()->perfil)['permiso'] ?? false;

        return $tienePermiso ? null : $this->error('No tienes permiso para ver las solicitudes', 403);
    }

    // POST /solicitudes — cualquier empleado autenticado
    public function crear(SolicitudInicialRequest $request)
    {
        $response = $this->solicitudesServices->crear(
            $request->toSolicitudData(),
            $request->toProductosData(),
            $request->user()->id_user,
            $request->user()->id_user,
        );

        return $this->apiResponse($response);
    }

    // GET /solicitudes?per-page=&id_user=&id_area=&estado=&fecha_desde=&fecha_hasta=&s= — opción 60
    public function listar(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $filtros = [
            'id_user' => $request->integer('id_user') ?: null,
            'id_area' => $request->integer('id_area') ?: null,
            'estado' => $request->input('estado'),
            'fecha_desde' => $request->input('fecha_desde'),
            'fecha_hasta' => $request->input('fecha_hasta'),
            's' => $request->input('s') ? trim($request->input('s')) : null,
        ];

        $response = $this->solicitudesServices->listar($filtros, $request->integer('per-page', 10));

        if ($response['error']) {
            return $this->apiResponse($response);
        }

        return $this->paginatedResponse($response);
    }

    // GET /solicitudes/{id} — opción 60
    public function ver(Request $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->solicitudesServices->ver($id));
    }

    // POST /solicitudes/{id}/verificar — opción 60
    public function verificar(VerificarSolicitudRequest $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->solicitudesServices->verificar(
            $id,
            $request->validated(),
            $request->user()->id_user,
        ));
    }

    // POST /solicitudes/{id}/asignar-proveedor — opción 60 (multipart: id_proveedor, iva, cotizacion_doc)
    public function asignarProveedor(AsignarProveedorRequest $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->solicitudesServices->asignarProveedor(
            $id,
            $request->validated(),
            $request->file('cotizacion_doc'),
            $request->user()->id_user,
        ));
    }

    // PUT /solicitudes/{id}/aplazar — opción 60
    public function aplazar(AplazarSolicitudRequest $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->solicitudesServices->aplazar(
            $id,
            $request->fecha_aplazado,
            $request->user()->id_user,
        ));
    }

    // PUT /solicitudes/{id}/rechazar — opción 60
    public function rechazar(RechazarSolicitudRequest $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->solicitudesServices->rechazar(
            $id,
            $request->validated(),
            $request->user()->id_user,
        ));
    }

    // POST /solicitudes/{id}/verificar-entrega — opción 60 (multipart: rubros + factura_doc + decision)
    public function verificarEntrega(VerificarEntregaRequest $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->solicitudesServices->verificarEntrega(
            $id,
            $request->validated(),
            $request->file('factura_doc'),
            $request->user()->id_user,
        ));
    }
}