<?php

namespace App\Http\Controllers\Cafeteria;

use App\Http\Controllers\Concerns\VerificaPermisos;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cafeteria\CategoriaProductoRequest;
use App\Http\Requests\Cafeteria\ConversionUnidadRequest;
use App\Http\Requests\Cafeteria\PrecioProductoRequest;
use App\Http\Requests\Cafeteria\ProductoRequest;
use App\Http\Requests\Cafeteria\UnidadMedidaRequest;
use App\Services\Cafeteria\CatalogoCafeteriaService;
use App\Support\Permiso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catálogo de la cafetería (opción 178): categorías, unidades, conversiones, productos y
 * precios. Los catálogos y la lista de productos también los lee quien tiene Proveedores y
 * productos (179). Reglas en CatalogoCafeteriaService.
 */
class CatalogoCafeteriaController extends Controller
{
    use VerificaPermisos;

    public function __construct(private CatalogoCafeteriaService $service)
    {
    }

    /** GET /api/cafeteria/catalogo/catalogos — categorías, unidades, conversiones, tipos y puntos (178 o 179). */
    public function catalogos(Request $request): JsonResponse
    {
        return $this->con($request, fn () => $this->service->catalogos(), Permiso::CAFETERIA_CATALOGO, Permiso::CAFETERIA_PROVEEDORES);
    }

    /** GET /api/cafeteria/productos?s=&id_categoria=&tipo_producto=&activo= (178 o 179) */
    public function productos(Request $request): JsonResponse
    {
        return $this->con($request, fn () => $this->service->listarProductos($request->only('s', 'id_categoria', 'tipo_producto', 'activo')), Permiso::CAFETERIA_CATALOGO, Permiso::CAFETERIA_PROVEEDORES);
    }

    /** GET /api/cafeteria/productos/{producto} — con precios y proveedores. */
    public function producto(Request $request, int $producto): JsonResponse
    {
        return $this->con($request, fn () => $this->service->detalleProducto($producto));
    }

    public function storeProducto(ProductoRequest $request): JsonResponse
    {
        return $this->con($request, fn () => $this->service->crearProducto($request->validated(), $request->user()->id_user));
    }

    public function updateProducto(ProductoRequest $request, int $producto): JsonResponse
    {
        return $this->con($request, fn () => $this->service->actualizarProducto($producto, $request->validated()));
    }

    /** POST /api/cafeteria/productos/{producto}/precios { id_punto_venta?, precio, vigencia_desde, vigencia_hasta? } */
    public function storePrecio(PrecioProductoRequest $request, int $producto): JsonResponse
    {
        return $this->con($request, fn () => $this->service->agregarPrecio($producto, $request->validated(), $request->user()->id_user));
    }

    /** DELETE /api/cafeteria/precios/{precio} — solo uno que aún no empieza. */
    public function destroyPrecio(Request $request, int $precio): JsonResponse
    {
        return $this->con($request, fn () => $this->service->eliminarPrecio($precio));
    }

    public function storeCategoria(CategoriaProductoRequest $request): JsonResponse
    {
        return $this->con($request, fn () => $this->service->crearCategoria($request->validated()));
    }

    public function updateCategoria(CategoriaProductoRequest $request, int $categoria): JsonResponse
    {
        return $this->con($request, fn () => $this->service->actualizarCategoria($categoria, $request->validated()));
    }

    public function storeUnidad(UnidadMedidaRequest $request): JsonResponse
    {
        return $this->con($request, fn () => $this->service->crearUnidad($request->validated()));
    }

    public function updateUnidad(UnidadMedidaRequest $request, int $unidad): JsonResponse
    {
        return $this->con($request, fn () => $this->service->actualizarUnidad($unidad, $request->validated()));
    }

    public function storeConversion(ConversionUnidadRequest $request): JsonResponse
    {
        return $this->con($request, fn () => $this->service->crearConversion($request->validated()));
    }

    public function updateConversion(ConversionUnidadRequest $request, int $conversion): JsonResponse
    {
        return $this->con($request, fn () => $this->service->actualizarConversion($conversion, $request->validated()));
    }

    /** Sin opciones explícitas, exige Catálogo (178). */
    private function con(Request $request, callable $accion, int ...$opciones): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, ...($opciones ?: [Permiso::CAFETERIA_CATALOGO]))) {
            return $rechazo;
        }

        return $this->apiResponse($accion());
    }
}
