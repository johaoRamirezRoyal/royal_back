<?php

namespace App\Http\Controllers\Reservas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reservas\StoreReservaRequest;
use App\Models\Reservas\Reservas;
use App\Services\Reservas\ReservasServices;
use App\Services\Usuarios\UsuariosServices;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReservaController extends Controller
{
    // cron_opciones bajo id_modulo=7 (Reservas) — ver migración
    // 2026_09_14_130000_seed_opcion_editar_reservas.
    private const OPCION_EDITAR_RESERVAS = 118;

    // Opción "/reservas" (41) — quien la tiene gestiona cancelar/finalizar de
    // cualquier reserva, sin la restricción de autoservicio de abajo.
    private const OPCION_GESTIONAR_RESERVAS = 41;

    // Días mínimos de anticipación que "Mis reservas" exige para que el propio dueño
    // edite/cancele su reserva sin pasar por OPCION_EDITAR_RESERVAS/OPCION_GESTIONAR_RESERVAS.
    private const DIAS_MIN_AUTOSERVICIO = 2;

    // Campos de edición completa (a diferencia de `cancelar`/`finalizar`, que son
    // autoservicio sin permiso propio — ver actualizarReserva más abajo).
    private const CAMPOS_EDICION = ['fecha_reserva', 'hora_reserva', 'id_salon', 'detalle_reserva', 'portatil', 'sonido'];

    /**
     * El propio dueño de la reserva puede editarla/cancelarla sin permiso especial,
     * pero solo mientras falten más de DIAS_MIN_AUTOSERVICIO días para la fecha
     * reservada — evita cambios de último momento que ya no le da tiempo de re-planear
     * a quien gestiona el salón. Quien tiene el permiso correspondiente (118/41) no
     * pasa por esta restricción, como ya no pasaba antes de este cambio.
     */
    private function puedeAutogestionar(Reservas $reserva, Request $request): bool
    {
        if ((int) $reserva->id_user !== (int) $request->user()->id_user) {
            return false;
        }

        return Carbon::today()->diffInDays(Carbon::parse($reserva->fecha_reserva), false) > self::DIAS_MIN_AUTOSERVICIO;
    }

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

        // Cancelar/finalizar y edición completa admiten dos rutas: quien tiene el
        // permiso del módulo (118 para editar, 41 para cancelar/finalizar) gestiona
        // cualquier reserva sin restricción; el propio dueño puede autogestionar la
        // suya (ver "Mis reservas") solo si faltan más de DIAS_MIN_AUTOSERVICIO días.
        $cancelarOFinalizar = $request->boolean('cancelar') || $request->boolean('finalizar');
        $esEdicionCompleta = !$cancelarOFinalizar && $request->hasAny(self::CAMPOS_EDICION);

        if ($esEdicionCompleta) {
            $tienePermiso = $this->usuariosService->tienePermiso(self::OPCION_EDITAR_RESERVAS, $request->user()->perfil)['permiso'] ?? false;

            if (!$tienePermiso && !$this->puedeAutogestionar($reserva, $request)) {
                return $this->error('No tienes permiso para editar esta reserva, o ya no tiene la anticipación mínima de ' . self::DIAS_MIN_AUTOSERVICIO . ' días para autogestionarla.', 403);
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
        } elseif ($cancelarOFinalizar) {
            $tienePermiso = $this->usuariosService->tienePermiso(self::OPCION_GESTIONAR_RESERVAS, $request->user()->perfil)['permiso'] ?? false;

            if (!$tienePermiso && !$this->puedeAutogestionar($reserva, $request)) {
                return $this->error('No tienes permiso para cancelar esta reserva, o ya no tiene la anticipación mínima de ' . self::DIAS_MIN_AUTOSERVICIO . ' días para autogestionarla.', 403);
            }
        }

        return $this->apiResponse(
            $this->reservasServices->actualizarReserva($request->all())
        );
    }
}
