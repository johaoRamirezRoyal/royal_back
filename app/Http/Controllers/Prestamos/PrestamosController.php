<?php

namespace App\Http\Controllers\Prestamos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Prestamos\StorePrestamoRequest;
use App\Http\Requests\Prestamos\UpdatePrestamoRequest;
use App\Services\Prestamos\PrestamosService;
use Illuminate\Http\Request;

class PrestamosController extends Controller
{
    public function __construct(
        private PrestamosService $prestamosService
    ) {}

    public function listarPrestamos(Request $request)
    {
        $response = $this->prestamosService->mostrarPrestamosInventario(
            descripcion:   $request->input('descripcion'),
            activo:        $request->boolean('activo') ?: null,
            devueltos:     $request->boolean('devueltos') ?: null,
            vencidos:      $request->boolean('vencidos') ?: null,
            idUserEntrega: $request->integer('id_user_entrega') ?: null,
            idUserPresta:  $request->integer('id_user_prestamo') ?: null,
            perpage:       $request->input('per_page', 10),
        );

        if ($response['error']) {
            return $this->apiResponse($response);
        }

        return $this->paginatedResponse($response);
    }

    public function crearPrestamo(StorePrestamoRequest $request)
    {
        return $this->apiResponse(
            $this->prestamosService->agregarPrestamos($request->validated())
        );
    }

    public function actualizarPrestamo(UpdatePrestamoRequest $request)
    {
        return $this->apiResponse(
            $this->prestamosService->actualizarPrestamo($request->validated())
        );
    }
}
