<?php

namespace App\Http\Controllers\ProcesoCompra;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcesoCompra\ProveedorBancoRequest;
use App\Http\Requests\ProcesoCompra\ProveedorContactoRequest;
use App\Http\Requests\ProcesoCompra\ProveedorRequest;
use App\Services\ProcesoCompra\ProveedoresServices;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProveedoresController extends Controller
{
    // Opción "Proveedores" (61) del módulo "Proceso de compra" (9). Otorgada hoy a:
    // Super Admin, Administrador, Tesorera, Asistente Contable.
    private const OPCION_PROVEEDORES = 61;

    public function __construct(
        private ProveedoresServices $proveedoresServices,
        private UsuariosServices $usuariosService,
    ) {}

    private function sinAcceso(Request $request): ?JsonResponse
    {
        $tienePermiso = $this->usuariosService->tienePermiso(self::OPCION_PROVEEDORES, $request->user()->perfil)['permiso'] ?? false;

        return $tienePermiso ? null : $this->error('No tienes permiso para gestionar proveedores', 403);
    }

    // GET /proveedores — lectura compartida (dropdown de solicitudes)
    public function listar()
    {
        return $this->apiResponse($this->proveedoresServices->listar());
    }

    // GET /proveedores/{id} — expone documentos/contactos/bancos, requiere opción
    public function ver(Request $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->proveedoresServices->ver($id));
    }

    // POST /proveedores
    public function crear(ProveedorRequest $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $response = $this->proveedoresServices->crear(
            $request->toUsuarioData(),
            $request->toProveedorData(),
            $request->user()->id_user,
        );

        return $this->apiResponse($response);
    }

    // PUT /proveedores/{id}
    public function actualizar(ProveedorRequest $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $response = $this->proveedoresServices->actualizar(
            $id,
            $request->toUsuarioData(),
            $request->toProveedorData(),
            $request->user()->id_user,
        );

        return $this->apiResponse($response);
    }

    // PUT /proveedores/{id}/estado  {"estado":"activo"|"inactivo"}
    public function cambiarEstado(Request $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $estado = $request->input('estado', 'activo');

        if (!in_array($estado, ['activo', 'inactivo'], true)) {
            return $this->error('El estado debe ser "activo" o "inactivo"', 422);
        }

        return $this->apiResponse($this->proveedoresServices->cambiarEstado($id, $estado, $request->user()->id_user));
    }

    // GET /proveedores/select — solo id y nombre para dropdown
    public function listarParaSelect(Request $request)
    {
        return $this->apiResponse($this->proveedoresServices->listarParaSelect());
    }

    // GET /proveedores/tipos-documento
    public function listarTiposDocumento(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->proveedoresServices->listarTiposDocumento());
    }

    // GET /proveedores/{id}/compras — compras (solicitudes formalizadas) de un proveedor.
    // Lectura compartida: opción 61 (Proveedores) u 60 (Listado de solicitudes).
    public function listarCompras(Request $request, int $id)
    {
        $perfil = $request->user()->perfil;
        $tieneProveedores = $this->usuariosService->tienePermiso(self::OPCION_PROVEEDORES, $perfil)['permiso'] ?? false;
        $tieneSolicitudes = $this->usuariosService->tienePermiso(60, $perfil)['permiso'] ?? false;

        if (!$tieneProveedores && !$tieneSolicitudes) {
            return $this->error('No tienes permiso para ver las compras del proveedor', 403);
        }

        return $this->apiResponse($this->proveedoresServices->listarCompras($id));
    }

    // GET /proveedores/{id}/documentos
    public function listarDocumentos(Request $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->proveedoresServices->listarDocumentos($id));
    }

    // POST /proveedores/{id}/documentos (multipart: archivo, tipo_documento, activo)
    public function subirDocumento(Request $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        if (!$request->hasFile('archivo')) {
            return $this->apiResponse([
                'error'   => true,
                'message' => 'Debe adjuntar el archivo del documento',
                'data'    => [],
            ]);
        }

        $tipoDocumento = $request->integer('tipo_documento');

        if (!$tipoDocumento) {
            return $this->apiResponse([
                'error'   => true,
                'message' => 'Debe indicar el tipo de documento',
                'data'    => [],
            ]);
        }

        $validator = Validator::make($request->all(), [
            'archivo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        if ($validator->fails()) {
            return $this->apiResponse([
                'error'   => true,
                'message' => $validator->errors()->first(),
                'data'    => [],
            ]);
        }

        return $this->apiResponse($this->proveedoresServices->subirDocumento(
            $id,
            $tipoDocumento,
            $request->has('activo') ? (int) $request->input('activo') : 1,
            $request->file('archivo'),
            $request->user()->id_user,
        ));
    }

    // PUT /proveedores/documentos/{docId} (multipart opcional)
    public function actualizarDocumento(Request $request, int $docId)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $tipoDocumento = $request->integer('tipo_documento');

        if (!$tipoDocumento) {
            return $this->apiResponse([
                'error'   => true,
                'message' => 'Debe indicar el tipo de documento',
                'data'    => [],
            ]);
        }

        $validator = Validator::make($request->all(), [
            'archivo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        if ($validator->fails()) {
            return $this->apiResponse([
                'error'   => true,
                'message' => $validator->errors()->first(),
                'data'    => [],
            ]);
        }

        return $this->apiResponse($this->proveedoresServices->actualizarDocumento(
            $docId,
            $tipoDocumento,
            $request->has('activo') ? (int) $request->input('activo') : 1,
            $request->file('archivo'),
            $request->user()->id_user,
        ));
    }

    // PUT /proveedores/documentos/{docId}/estado {"activo":0|1}
    public function cambiarEstadoDocumento(Request $request, int $docId)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $activo = $request->input('activo');

        if (!in_array($activo, [0, 1], true)) {
            return $this->error('El estado debe ser 0 o 1', 422);
        }

        return $this->apiResponse($this->proveedoresServices->cambiarEstadoDocumento($docId, $activo, $request->user()->id_user));
    }

    // DELETE /proveedores/documentos/{docId}
    public function eliminarDocumento(Request $request, int $docId)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->proveedoresServices->eliminarDocumento($docId));
    }

    // GET /proveedores/{id}/contactos
    public function listarContactos(Request $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->proveedoresServices->listarContactos($id));
    }

    // POST /proveedores/{id}/contactos
    public function crearContacto(ProveedorContactoRequest $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->proveedoresServices->crearContacto(
            $id,
            $request->toContactoData(),
            $request->user()->id_user,
        ));
    }

    // PUT /proveedores/contactos/{contactoId}
    public function actualizarContacto(ProveedorContactoRequest $request, int $contactoId)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->proveedoresServices->actualizarContacto(
            $contactoId,
            $request->toContactoData(),
            $request->user()->id_user,
        ));
    }

    // PUT /proveedores/contactos/{contactoId}/estado {"activo":0|1}
    public function cambiarEstadoContacto(Request $request, int $contactoId)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $activo = $request->input('activo');

        if (!in_array($activo, [0, 1], true)) {
            return $this->error('El estado debe ser 0 o 1', 422);
        }

        return $this->apiResponse($this->proveedoresServices->cambiarEstadoContacto($contactoId, $activo, $request->user()->id_user));
    }

    // DELETE /proveedores/contactos/{contactoId}
    public function eliminarContacto(Request $request, int $contactoId)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->proveedoresServices->eliminarContacto($contactoId));
    }

    // GET /proveedores/{id}/bancos
    public function listarBancos(Request $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->proveedoresServices->listarBancos($id));
    }

    // POST /proveedores/{id}/bancos
    public function crearBanco(ProveedorBancoRequest $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->proveedoresServices->crearBanco(
            $id,
            $request->toBancoData(),
            $request->user()->id_user,
        ));
    }

    // PUT /proveedores/bancos/{bancoId}
    public function actualizarBanco(ProveedorBancoRequest $request, int $bancoId)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->proveedoresServices->actualizarBanco(
            $bancoId,
            $request->toBancoData(),
            $request->user()->id_user,
        ));
    }

    // PUT /proveedores/bancos/{bancoId}/estado {"activo":0|1}
    public function cambiarEstadoBanco(Request $request, int $bancoId)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $activo = $request->input('activo');

        if (!in_array($activo, [0, 1], true)) {
            return $this->error('El estado debe ser 0 o 1', 422);
        }

        return $this->apiResponse($this->proveedoresServices->cambiarEstadoBanco($bancoId, $activo, $request->user()->id_user));
    }

    // DELETE /proveedores/bancos/{bancoId}
    public function eliminarBanco(Request $request, int $bancoId)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse($this->proveedoresServices->eliminarBanco($bancoId));
    }
}