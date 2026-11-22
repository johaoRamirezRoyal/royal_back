<?php

namespace App\Http\Controllers\Cafeteria;

use App\Http\Controllers\Concerns\VerificaPermisos;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cafeteria\ProveedorProductoRequest;
use App\Services\Cafeteria\ProveedoresCafeteriaService;
use App\Support\Permiso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Proveedores y productos de la cafetería (opción 179). El proveedor es el de Compras; aquí solo
 * qué productos ofrece. Reglas en ProveedoresCafeteriaService.
 */
class ProveedoresCafeteriaController extends Controller
{
    use VerificaPermisos;

    public function __construct(private ProveedoresCafeteriaService $service)
    {
    }

    /** GET /api/cafeteria/proveedores?s= */
    public function index(Request $request): JsonResponse
    {
        return $this->con($request, fn () => $this->service->proveedores($request->input('s')));
    }

    /** GET /api/cafeteria/proveedores/{proveedor}/productos */
    public function productos(Request $request, int $proveedor): JsonResponse
    {
        return $this->con($request, fn () => $this->service->productosDeProveedor($proveedor));
    }

    /** POST /api/cafeteria/proveedor-productos { id_proveedor, id_producto, codigo_producto_proveedor?, precio_referencia?, cantidad_minima?, es_preferido? } */
    public function store(ProveedorProductoRequest $request): JsonResponse
    {
        return $this->con($request, fn () => $this->service->vincular($request->validated()));
    }

    /** PUT /api/cafeteria/proveedor-productos/{vinculo} */
    public function update(ProveedorProductoRequest $request, int $vinculo): JsonResponse
    {
        return $this->con($request, fn () => $this->service->actualizarVinculo($vinculo, $request->validated()));
    }

    private function con(Request $request, callable $accion): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::CAFETERIA_PROVEEDORES)) {
            return $rechazo;
        }

        return $this->apiResponse($accion());
    }
}
