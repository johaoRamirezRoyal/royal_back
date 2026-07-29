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
