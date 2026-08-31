<?php

namespace App\Http\Controllers\ProcesoCompra;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcesoCompra\AgregarInventarioSolicitudRequest;
use App\Http\Requests\ProcesoCompra\AplazarSolicitudRequest;
use App\Http\Requests\ProcesoCompra\AprobarSolicitudRequest;
use App\Http\Requests\ProcesoCompra\AsignarProveedorRequest;
use App\Http\Requests\ProcesoCompra\RechazarSolicitudInicialRequest;
use App\Http\Requests\ProcesoCompra\RechazarSolicitudRequest;
use App\Http\Requests\ProcesoCompra\SolicitudInicialRequest;
use App\Http\Requests\ProcesoCompra\VerificarEntregaRequest;
use App\Http\Requests\ProcesoCompra\VerificarSolicitudRequest;
use App\Models\ProcesoCompra\Solicitudes\SolicitudInicial;
use App\Services\inventario\InventarioServices;
use App\Services\ProcesoCompra\SolicitudesServices;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SolicitudesController extends Controller
{
    // Opción "Listado de solicitudes" (60) del módulo "Proceso de compra" (9) — legada,
    // sigue gateando los endpoints que no forman parte del rediseño (ver/verificar/
    // aplazar/rechazar sobre la solicitud final, agregar a inventario).
    private const OPCION_SOLICITUDES = 60;
    // "Compras — Gestión de compras" (109): seguimiento, confirmar/asignar proveedor,
    // disponible en stock, anular. "Compras — Ventas" (110): cambiar estado tras la
    // entrega. Ventas tiene ambas (ver migración eliminar_perfil_compras...) — no existe
    // un perfil "Compras" separado, resultó innecesario.
    private const OPCION_COMPRAS_GESTION = 109;
    private const OPCION_COMPRAS_VENTAS = 110;
    // Mismo perfil Coordinador (26) que EvaluacionesServices — aprueba/rechaza las
    // solicitudes de usuarios de su propio id_nivel. Super Admin(1)/Administrador(2)
    // también pueden ver y decidir la bandeja de aprobaciones, sin recorte de nivel.
    private const PERFIL_COORDINADOR = 26;
    private const PERFIL_SUPER_ADMIN = 1;
    private const PERFIL_ADMIN = 2;

    public function __construct(
        private SolicitudesServices $solicitudesServices,
        private InventarioServices $inventarioServices,
        private UsuariosServices $usuariosService,
    ) {}

    private function sinAcceso(Request $request, int ...$opciones): ?JsonResponse
    {
        $tienePermiso = count($opciones) === 1
            ? ($this->usuariosService->tienePermiso($opciones[0], $request->user()->perfil)['permiso'] ?? false)
            : ($this->usuariosService->tieneAlgunPermiso($opciones, $request->user()->perfil)['permiso'] ?? false);

        return $tienePermiso ? null : $this->error('No tienes permiso para ver las solicitudes', 403);
    }

    private function esAdminGeneral(Request $request): bool
    {
        return in_array((int) $request->user()->perfil, [self::PERFIL_SUPER_ADMIN, self::PERFIL_ADMIN], true);
    }

    private function noPuedeGestionarAprobaciones(Request $request): ?JsonResponse
    {
        if ((int) $request->user()->perfil === self::PERFIL_COORDINADOR || $this->esAdminGeneral($request)) {
            return null;
        }

        return $this->error('No tienes permiso para gestionar estas solicitudes', 403);
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

    // GET /solicitudes/mias — cualquier empleado autenticado, siempre acotado a su propio
    // id_user (nunca tomado del cliente).
    public function misSolicitudes(Request $request)
    {
        return $this->apiResponse($this->solicitudesServices->misSolicitudes($request->user()->id_user));
    }

    // POST /solicitudes/{id}/cancelar — cualquier empleado autenticado, pero solo puede
    // cancelar sus propias solicitudes (verificado en el servicio) y solo mientras el
    // coordinador no las haya decidido.
    public function cancelar(Request $request, int $id)
    {
        return $this->apiResponse($this->solicitudesServices->cancelar(
            $id,
            $request->user()->id_user,
            $request->user()->id_user,
        ));
    }

    // GET /solicitudes/seguimiento?fecha_desde=&fecha_hasta=&id_user=&s=&id_nivel=&perfil=
    // — opción 109 (Ventas + Super Admin/Administrador). id_nivel/perfil solo se honran si el perfil es Super
    // Admin(1)/Administrador(2); para cualquier otro perfil con la opción, se ignoran —
    // Se ven todas las aprobadas sin recorte por nivel.
    public function seguimiento(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_COMPRAS_GESTION)) {
            return $rechazo;
        }

        $esAdmin = in_array((int) $request->user()->perfil, [1, 2], true);

        $filtros = [
            'id_user' => $request->integer('id_user') ?: null,
            'fecha_desde' => $request->input('fecha_desde'),
            'fecha_hasta' => $request->input('fecha_hasta'),
            's' => $request->input('s') ? trim($request->input('s')) : null,
            'id_nivel' => $esAdmin ? ($request->integer('id_nivel') ?: null) : null,
            'perfil' => $esAdmin ? ($request->integer('perfil') ?: null) : null,
        ];

        return $this->apiResponse($this->solicitudesServices->listarSeguimiento($filtros));
    }

    // POST /solicitudes/{id}/anular — opción 109 (Ventas + Super Admin/Administrador)
    // Anula la solicitud para ocultarla del seguimiento.
    public function anular(Request $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_COMPRAS_GESTION)) {
            return $rechazo;
        }

        return $this->apiResponse($this->solicitudesServices->anular($id, $request->user()->id_user));
    }

    // GET /solicitudes?per-page=&estado=&fecha_desde=&fecha_hasta=&s=&perfil=&id_nivel= —
    // bandeja de aprobaciones. Coordinador (26): siempre acotada a su propio id_nivel,
    // nunca tomado del cliente. Super Admin(1)/Administrador(2): ven todas, sin importar
    // el nivel, con id_nivel como filtro opcional.
    public function listar(Request $request)
    {
        if ($rechazo = $this->noPuedeGestionarAprobaciones($request)) {
            return $rechazo;
        }

        $esAdmin = $this->esAdminGeneral($request);

        $filtros = [
            'id_nivel' => $esAdmin ? ($request->integer('id_nivel') ?: null) : $request->user()->id_nivel,
            'perfil' => $request->integer('perfil') ?: null,
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

    // POST /solicitudes/{id}/aprobar — coordinador (solo su propio nivel) o Super
    // Admin/Administrador (cualquier nivel).
    public function aprobar(AprobarSolicitudRequest $request, int $id)
    {
        if ($rechazo = $this->noPuedeGestionarAprobaciones($request)) {
            return $rechazo;
        }

        $inicial = SolicitudInicial::with('usuario:id_user,id_nivel')->find($id);

        if (!$inicial) {
            return $this->error('Solicitud no encontrada', 404);
        }

        if (!$this->esAdminGeneral($request) && (int) $inicial->usuario?->id_nivel !== (int) $request->user()->id_nivel) {
            return $this->error('No puedes aprobar solicitudes de otro nivel', 403);
        }

        return $this->apiResponse($this->solicitudesServices->aprobar(
            $id,
            $request->input('observacion'),
            $request->user()->id_user,
        ));
    }

    // POST /solicitudes/{id}/rechazar-inicial — coordinador (solo su propio nivel) o
    // Super Admin/Administrador (cualquier nivel). Nombre distinto de `PUT .../rechazar`
    // a propósito: ese opera sobre la solicitud FINAL (post-aprobación), este sobre la
    // solicitud INICIAL (pre-aprobación).
    public function rechazarInicial(RechazarSolicitudInicialRequest $request, int $id)
    {
        if ($rechazo = $this->noPuedeGestionarAprobaciones($request)) {
            return $rechazo;
        }

        $inicial = SolicitudInicial::with('usuario:id_user,id_nivel')->find($id);

        if (!$inicial) {
            return $this->error('Solicitud no encontrada', 404);
        }

        if (!$this->esAdminGeneral($request) && (int) $inicial->usuario?->id_nivel !== (int) $request->user()->id_nivel) {
            return $this->error('No puedes rechazar solicitudes de otro nivel', 403);
        }

        return $this->apiResponse($this->solicitudesServices->rechazarInicial(
            $id,
            $request->input('motivo'),
            $request->user()->id_user,
        ));
    }

    // POST /solicitudes/{id}/disponible-stock — opción 109 (Ventas + Super Admin/Administrador). Alternativa a
    // asignar-proveedor: no se inicia compra, se resuelve con stock existente.
    public function disponibleStock(Request $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_COMPRAS_GESTION)) {
            return $rechazo;
        }

        return $this->apiResponse($this->solicitudesServices->marcarDisponibleStock($id, $request->user()->id_user));
    }

    // GET /solicitudes/{id} — opción 60
    public function ver(Request $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_SOLICITUDES)) {
            return $rechazo;
        }

        return $this->apiResponse($this->solicitudesServices->ver($id));
    }

    // POST /solicitudes/{id}/verificar — opción 60
    public function verificar(VerificarSolicitudRequest $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_SOLICITUDES)) {
            return $rechazo;
        }

        return $this->apiResponse($this->solicitudesServices->verificar(
            $id,
            $request->validated(),
            $request->user()->id_user,
        ));
    }

    // POST /solicitudes/{id}/asignar-proveedor — opción 109 (Ventas + Super Admin/Administrador, multipart: id_proveedor, iva, cotizacion_doc)
    public function asignarProveedor(AsignarProveedorRequest $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_COMPRAS_GESTION)) {
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
        if ($rechazo = $this->sinAcceso($request, self::OPCION_SOLICITUDES)) {
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
        if ($rechazo = $this->sinAcceso($request, self::OPCION_SOLICITUDES)) {
            return $rechazo;
        }

        return $this->apiResponse($this->solicitudesServices->rechazar(
            $id,
            $request->validated(),
            $request->user()->id_user,
        ));
    }

    // POST /solicitudes/{id}/verificar-entrega — opción 110 (Ventas, multipart: rubros + factura_doc + decision)
    public function verificarEntrega(VerificarEntregaRequest $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_COMPRAS_VENTAS)) {
            return $rechazo;
        }

        return $this->apiResponse($this->solicitudesServices->verificarEntrega(
            $id,
            $request->validated(),
            $request->file('factura_doc'),
            $request->user()->id_user,
        ));
    }

    // POST /solicitudes/{id}/agregar-inventario — opción 60
    // Agrega al inventario los artículos de la solicitud (JSON: articulos[]).
    public function agregarInventario(AgregarInventarioSolicitudRequest $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_SOLICITUDES)) {
            return $rechazo;
        }

        return $this->apiResponse($this->inventarioServices->agregarArticulosAInventario(
            $id,
            $request->input('articulos'),
            $request->user()->id_user,
        ));
    }
}