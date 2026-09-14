<?php

namespace App\Http\Controllers\Reservas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reservas\StoreReservaRequest;
use App\Models\Reservas\Reservas;
use App\Services\Reservas\ReservasServices;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReservaController extends Controller
{
    // cron_opciones bajo id_modulo=7 (Reservas) — ver migración
    // 2026_09_14_130000_seed_opcion_editar_reservas.
    private const OPCION_EDITAR_RESERVAS = 118;

    // Campos de edición completa (a diferencia de `cancelar`/`finalizar`, que son
    // autoservicio sin permiso propio — ver actualizarReserva más abajo).
    private const CAMPOS_EDICION = ['fecha_reserva', 'hora_reserva', 'id_salon', 'detalle_reserva', 'portatil', 'sonido'];

    public function __construct(
        private ReservasServices $reservasServices,
        private UsuariosServices $usuariosService,
    ) {}

    public function crearReserva(StoreReservaRequest $request)
    {
        return $this->apiResponse(
            $this->reservasServices->crearReserva($request->validated())
        );
    }

    // Igual que crearReserva/listarReservas: acceso general, sin opción de permisos —
    // reservar (y consultar disponibilidad antes de reservar) es para cualquier
    // autenticado, no solo quien administra el catálogo de salones (opción 40).
    public function disponibilidadPortatil(Request $request)
    {
        $fecha = $request->input('fecha');
        $hora = $request->input('hora');

        if (!$fecha || !strtotime($fecha) || !$hora) {
            return $this->error('Debe indicar fecha (Y-m-d) y hora.', 422);
        }

        return $this->apiResponse([
            'error' => false,
            'message' => 'Disponibilidad obtenida correctamente.',
            'data' => $this->reservasServices->validarDisponibilidadPortatil($fecha, (int) $hora),
        ]);
    }

    public function listarReservas(Request $request)
    {
        $response = $this->reservasServices->mostrarReservas(
            id_user: $request->input('id_user'),
            id_salon: $request->input('id_salon'),
            fechaReserva: $request->input('fecha_reserva'),
            fechaDesde: $request->input('fecha_desde'),
            fechaHasta: $request->input('fecha_hasta'),
            cancelado: $request->boolean('cancelado') ?: null,
            perpage: $request->input('per_page', 10),
        );

        if ($response['error']) {
            return $this->apiResponse($response);
        }

        return $this->paginatedResponse($response);
    }

    public function actualizarReserva(Request $request)
    {
        $reserva = Reservas::find($request->input('id'));

        if (!$reserva) {
            return $this->error('No se encontró la reserva.', 404);
        }

        // Cancelar/finalizar: autoservicio, igual que crearReserva — sin opción de
        // permisos. Cualquier otro campo es edición completa y exige OPCION_EDITAR_RESERVAS.
        $esEdicionCompleta = !$request->boolean('cancelar') && !$request->boolean('finalizar')
            && $request->hasAny(self::CAMPOS_EDICION);

        if ($esEdicionCompleta) {
            $tienePermiso = $this->usuariosService->tienePermiso(self::OPCION_EDITAR_RESERVAS, $request->user()->perfil)['permiso'] ?? false;

            if (!$tienePermiso) {
                return $this->error('No tienes permiso para editar reservas.', 403);
            }

            $validator = Validator::make($request->all(), [
                'fecha_reserva' => ['sometimes', 'date_format:Y-m-d'],
                'hora_reserva' => ['sometimes', 'integer', 'exists:horas,id'],
                'id_salon' => ['sometimes', 'integer', 'exists:salones,id'],
                'detalle_reserva' => ['sometimes', 'nullable', 'string', 'max:1000'],
                'portatil' => ['sometimes', 'integer', 'min:0'],
                'sonido' => ['sometimes', 'boolean'],
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), 422);
            }
        }

        return $this->apiResponse(
            $this->reservasServices->actualizarReserva($request->all())
        );
    }
}
