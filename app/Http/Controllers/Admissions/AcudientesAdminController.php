<?php

namespace App\Http\Controllers\Admissions;

use App\Http\Controllers\Controller;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestión (listar/buscar/activar-desactivar) de los acudientes que se auto-registran vía
 * el flujo público de Admisiones (perfil 6 en `usuarios`, ver
 * AdmissionsController::familyRegister). No hay creación/edición acá — el acudiente se
 * registra solo; esto es solo visibilidad + el interruptor de estado, mismo campo que ya
 * usa PrivateAdmissionsRoute (frontend) para cerrarle la sesión a una cuenta inactiva.
 */
class AcudientesAdminController extends Controller
{
    private const OPCION_GESTION = 106;

    public function __construct(private UsuariosServices $usuariosService)
    {
    }

    private function sinAcceso(Request $request): ?JsonResponse
    {
        $perfil = $request->user()->perfil;

        if ($this->usuariosService->tienePermiso(self::OPCION_GESTION, $perfil)['permiso'] ?? false) {
            return null;
        }

        return $this->error('No tienes permiso para esta acción', 403);
    }

    public function index(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $response = $this->usuariosService->mostrarAcudientesPaginados(
            (int) $request->input('per-page', 10),
            $request->input('s', $request->input('busqueda')),
            $request->input('estado'),
            $request->input('sort', 'nombre'),
            $request->input('dir', 'asc'),
            $request->boolean('solo_autoregistrados'),
        );

        if ($response['error']) {
            return $this->error($response['message']);
        }

        return $this->paginatedResponse($response);
    }

    public function actualizarEstado(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $ids = $request->input('ids', []);
        $estado = $request->input('estado', 'activo');

        if (empty($ids) || ! is_array($ids)) {
            return $this->error('Debes proporcionar un array de IDs de acudientes a cambiar el estado', 400);
        }

        $response = $this->usuariosService->actualizarEstadoAcudientes($ids, $estado);

        if ($response['error']) {
            return $this->error($response['message']);
        }

        return $this->success($response['message']);
    }
}
