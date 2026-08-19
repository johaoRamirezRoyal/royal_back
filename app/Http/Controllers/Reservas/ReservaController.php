<?php

namespace App\Http\Controllers\Reservas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reservas\StoreReservaRequest;
use App\Models\Reservas\Reservas;
use App\Services\Reservas\ReservasServices;
use Illuminate\Http\Request;

class ReservaController extends Controller
{
    public function __construct(
        private ReservasServices $reservasServices
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

        return $this->apiResponse(
            $this->reservasServices->actualizarReserva($request->all())
        );
    }
}
