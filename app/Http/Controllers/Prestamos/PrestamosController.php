<?php

namespace App\Http\Controllers\Prestamos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Prestamos\StorePrestamoRequest;
use App\Http\Requests\Prestamos\UpdatePrestamoRequest;
use App\Services\Prestamos\PrestamosService;
use Illuminate\Http\Request;
use App\Support\Permiso;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PrestamosController extends Controller implements HasMiddleware
{
    /** Mis inventarios o Préstamos. Mismo permiso que su ruta: el controller no depende de que la ruta lo tenga. */
    public static function middleware(): array
    {
        return [new Middleware(Permiso::ruta([Permiso::MIS_INVENTARIOS, Permiso::PRESTAMOS_INVENTARIO]))];
    }

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
