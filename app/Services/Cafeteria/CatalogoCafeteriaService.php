<?php

namespace App\Services\Cafeteria;

use App\Enums\Cafeteria\EstadoCafeteria;
use App\Enums\Cafeteria\TipoProducto;
use App\Models\Cafeteria\CategoriaProducto;
use App\Models\Cafeteria\ConversionUnidad;
use App\Models\Cafeteria\PrecioProducto;
use App\Models\Cafeteria\Producto;
use App\Models\Cafeteria\PuntoVenta;
use App\Models\Cafeteria\UnidadMedida;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Catálogo de la cafetería (opción 178): categorías, unidades de medida, conversiones,
 * productos y sus precios con vigencia. Reglas:
 * - Códigos del sistema: CAT-001, PRD-0001; la unidad, de su abreviatura.
 * - Una categoría no puede quedar debajo de sí misma ni de una de sus hijas.
 * - No se desactiva una categoría o unidad que usa un producto activo.
 * - Un producto se vende, controla inventario o ambas cosas (si no, no tiene sentido).
 * - Precios: solo de productos vendibles; empiezan hoy o después (el pasado no se reescribe);
 *   las vigencias de un mismo producto y alcance (general o un punto) no se cruzan. Un precio
 *   nuevo cierra el abierto anterior el día antes de empezar.
 */
class CatalogoCafeteriaService
{
    use EjecutaOperacion;
    use GeneraCodigo;

    /** Catálogos para los formularios del catálogo (178 o 179). */
    public function catalogos(): array
    {
        try {
            return ['error' => false, 'message' => 'Catálogos del catálogo de productos', 'data' => [
                'categorias' => CategoriaProducto::orderBy('nombre')->get(['id', 'id_categoria_padre', 'codigo', 'nombre', 'descripcion', 'activo']),
                'unidades' => UnidadMedida::orderBy('nombre')->get(['id', 'codigo', 'nombre', 'abreviatura', 'precision_decimal', 'activo']),
                'conversiones' => ConversionUnidad::orderBy('id')->get(['id', 'id_unidad_origen', 'id_unidad_destino', 'factor_conversion', 'activa']),
                'tipos_producto' => array_map(fn (TipoProducto $t) => ['valor' => $t->value, ...$t->marcasSugeridas()], TipoProducto::cases()),
                'puntos_venta' => PuntoVenta::query()
                    ->join('caf_cafeterias as c', 'c.id', '=', 'caf_puntos_venta.id_cafeteria')
                    ->where('c.estado', '!=', EstadoCafeteria::CERRADA->value)
                    ->orderBy('c.nombre')->orderBy('caf_puntos_venta.nombre')
                    ->get(['caf_puntos_venta.id', 'caf_puntos_venta.codigo', 'caf_puntos_venta.nombre', 'caf_puntos_venta.id_cafeteria', 'c.nombre as cafeteria']),
            ]];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ─── Categorías ─────────────────────────────────────────────

    public function crearCategoria(array $datos): array
    {
        return $this->operacion(function () use ($datos) {
            $this->validarPadre(null, $datos['id_categoria_padre'] ?? null);

            return $this->crearConConsecutivo(CategoriaProducto::query(), 'CAT', fn (string $codigo) => CategoriaProducto::create([...$datos, 'codigo' => $codigo]));
        }, 'Categoría creada');
    }

    public function actualizarCategoria(int $id, array $datos): array
    {
        return $this->operacion(function () use ($id, $datos) {
            $categoria = CategoriaProducto::lockForUpdate()->find($id) ?? throw ReglaCafeteria::noExiste('La categoría no existe');

            // El padre se valida si cambia o si la categoría se reactiva: una categoría archivada
            // bajo un padre inactivo se puede seguir editando.
            $idPadre = array_key_exists('id_categoria_padre', $datos)
                ? ($datos['id_categoria_padre'] === null ? null : (int) $datos['id_categoria_padre'])
                : $categoria->id_categoria_padre;
            $seReactiva = ($datos['activo'] ?? $categoria->activo) && ! $categoria->activo;
            if ($idPadre !== $categoria->id_categoria_padre || $seReactiva) {
                $this->validarPadre($categoria->id, $idPadre);
            }

            if (isset($datos['activo']) && ! $datos['activo'] && $categoria->activo) {
                if ($categoria->productos()->where('activo', true)->exists()) {
                    throw new ReglaCafeteria('La categoría tiene productos activos: muévelos o desactívalos primero');
                }
                if ($categoria->hijas()->where('activo', true)->exists()) {
                    throw new ReglaCafeteria('La categoría tiene subcategorías activas: desactívalas primero');
                }
            }

            $categoria->update(self::sinCodigo($datos));

            return $categoria;
        }, 'Categoría actualizada');
    }

    /** El padre existe, está activo y no es la misma categoría ni una de sus descendientes. */
    private function validarPadre(?int $idCategoria, ?int $idPadre): void
    {
        if ($idPadre === null) {
            return;
        }

        $padre = CategoriaProducto::find($idPadre) ?? throw new ReglaCafeteria('La categoría padre no existe');
        if (! $padre->activo) {
            throw new ReglaCafeteria('La categoría padre está inactiva');
        }

        // Sube por los ancestros del padre: si aparece la categoría, sería un ciclo.
        for ($actual = $padre, $vueltas = 0; $actual && $vueltas < 50; $actual = $actual->padre, $vueltas++) {
            if ($idCategoria !== null && $actual->id === $idCategoria) {
                throw new ReglaCafeteria('Una categoría no puede quedar dentro de sí misma ni de una de sus subcategorías');
            }
        }
    }

    // ─── Unidades y conversiones ────────────────────────────────

    public function crearUnidad(array $datos): array
    {
        return $this->operacion(
            fn () => $this->crearConCodigoDeNombre(UnidadMedida::query(), $datos['abreviatura'], 30, fn (string $codigo) => UnidadMedida::create([...$datos, 'codigo' => $codigo]), 'UNIDAD'),
            'Unidad creada',
        );
    }

    public function actualizarUnidad(int $id, array $datos): array
    {
        return $this->operacion(function () use ($id, $datos) {
            $unidad = UnidadMedida::lockForUpdate()->find($id) ?? throw ReglaCafeteria::noExiste('La unidad no existe');

            if (isset($datos['activo']) && ! $datos['activo'] && $unidad->activo
                && Producto::where('id_unidad_base', $unidad->id)->where('activo', true)->exists()) {
                throw new ReglaCafeteria('Hay productos activos que usan esta unidad');
            }

            $unidad->update(self::sinCodigo($datos));

            return $unidad;
        }, 'Unidad actualizada');
    }

    /** @param array{id_unidad_origen:int, id_unidad_destino:int, factor_conversion:numeric} $datos */
    public function crearConversion(array $datos): array
    {
        return $this->operacion(function () use ($datos) {
            [$origen, $destino] = [(int) $datos['id_unidad_origen'], (int) $datos['id_unidad_destino']];

            if ($origen === $destino) {
                throw new ReglaCafeteria('La unidad de origen y la de destino deben ser distintas');
            }
            if (UnidadMedida::whereIn('id', [$origen, $destino])->where('activo', true)->count() !== 2) {
                throw new ReglaCafeteria('Las dos unidades deben existir y estar activas');
            }
            if (ConversionUnidad::where(fn ($q) => $q->where('id_unidad_origen', $origen)->where('id_unidad_destino', $destino))
                ->orWhere(fn ($q) => $q->where('id_unidad_origen', $destino)->where('id_unidad_destino', $origen))->exists()) {
                throw new ReglaCafeteria('Ya existe una conversión entre esas dos unidades (en uno u otro sentido)');
            }

            return ConversionUnidad::create([...$datos, 'activa' => $datos['activa'] ?? true]);
        }, 'Conversión creada');
    }

    /** Solo el factor y si está activa: cambiar las unidades sería otra conversión. */
    public function actualizarConversion(int $id, array $datos): array
    {
        return $this->operacion(function () use ($id, $datos) {
            $conversion = ConversionUnidad::lockForUpdate()->find($id) ?? throw ReglaCafeteria::noExiste('La conversión no existe');
            $conversion->update(array_intersect_key($datos, array_flip(['factor_conversion', 'activa'])));

            return $conversion;
        }, 'Conversión actualizada');
    }

    // ─── Productos ──────────────────────────────────────────────

    /** @param array{s?:string, id_categoria?:int, tipo_producto?:string, activo?:string} $filtros */
    public function listarProductos(array $filtros): array
    {
        try {
            $s = trim((string) ($filtros['s'] ?? ''));
            $hoy = CarbonImmutable::today()->toDateString();

            $productos = Producto::query()
                ->with(['categoria:id,codigo,nombre', 'unidadBase:id,codigo,abreviatura,precision_decimal'])
                ->addSelect(['precio_general' => PrecioProducto::select('precio')
                    ->whereColumn('id_producto', 'caf_productos.id')
                    ->whereNull('id_punto_venta')
                    ->where('vigencia_desde', '<=', $hoy)
                    ->where(fn ($q) => $q->whereNull('vigencia_hasta')->orWhere('vigencia_hasta', '>=', $hoy))
                    ->orderByDesc('vigencia_desde')
                    ->limit(1),
                ])
                ->when($s !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('nombre', 'like', "%{$s}%")
                    ->orWhere('codigo', 'like', "%{$s}%")
                    ->orWhere('codigo_barras', $s)))
                ->when(! empty($filtros['id_categoria']), fn ($q) => $q->where('id_categoria', (int) $filtros['id_categoria']))
                ->when(! empty($filtros['tipo_producto']), fn ($q) => $q->where('tipo_producto', $filtros['tipo_producto']))
                ->when(isset($filtros['activo']) && $filtros['activo'] !== '', fn ($q) => $q->where('activo', filter_var($filtros['activo'], FILTER_VALIDATE_BOOLEAN)))
                ->orderBy('nombre')
                ->get();

            return ['error' => false, 'message' => 'Productos', 'data' => $productos];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function detalleProducto(int $id): array
    {
        try {
            $producto = Producto::with([
                'categoria:id,codigo,nombre',
                'unidadBase:id,codigo,nombre,abreviatura,precision_decimal',
                'precios' => fn ($q) => $q->orderByRaw('id_punto_venta is not null')->orderBy('id_punto_venta')->orderByDesc('vigencia_desde'),
                'precios.puntoVenta:id,codigo,nombre,id_cafeteria',
                'precios.puntoVenta.cafeteria:id,codigo,nombre',
                'proveedores' => fn ($q) => $q->orderByDesc('es_preferido'),
            ])->find($id);

            if (! $producto) {
                return ['error' => true, 'message' => 'El producto no existe', 'status' => 404];
            }

            $nombres = ProveedoresCafeteriaService::nombres($producto->proveedores->pluck('id_proveedor')->all());
            $producto->proveedores->each(fn ($p) => $p->setAttribute('proveedor', $nombres[$p->id_proveedor] ?? null));

            return ['error' => false, 'message' => 'Producto', 'data' => $producto];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function crearProducto(array $datos, int $idUser): array
    {
        return $this->operacion(function () use ($datos, $idUser) {
            $this->validarProducto($datos, null);

            return $this->crearConConsecutivo(Producto::query(), 'PRD', fn (string $codigo) => Producto::create([
                ...$datos,
                'codigo' => $codigo,
                'creado_por' => $idUser,
            ]), 4);
        }, 'Producto creado');
    }

    public function actualizarProducto(int $id, array $datos): array
    {
        return $this->operacion(function () use ($id, $datos) {
            $producto = Producto::lockForUpdate()->find($id) ?? throw ReglaCafeteria::noExiste('El producto no existe');
            $this->validarProducto([...$producto->only(['id_categoria', 'id_unidad_base', 'es_vendible', 'controla_inventario']), ...$datos], $producto);
            $producto->update(self::sinCodigo($datos));

            return $producto;
        }, 'Producto actualizado');
    }

    private function validarProducto(array $datos, ?Producto $actual): void
    {
        if (! ($datos['es_vendible'] ?? true) && ! ($datos['controla_inventario'] ?? true)) {
            throw new ReglaCafeteria('Un producto se vende, controla inventario o ambas cosas');
        }

        // Una categoría o unidad inactiva no se elige de nuevo, pero un producto que ya la tenía se puede editar.
        if ($actual?->id_categoria !== (int) $datos['id_categoria'] && ! CategoriaProducto::where('id', $datos['id_categoria'])->where('activo', true)->exists()) {
            throw new ReglaCafeteria('La categoría no existe o está inactiva');
        }
        if ($actual?->id_unidad_base !== (int) $datos['id_unidad_base'] && ! UnidadMedida::where('id', $datos['id_unidad_base'])->where('activo', true)->exists()) {
            throw new ReglaCafeteria('La unidad no existe o está inactiva');
        }
    }

    // ─── Precios ────────────────────────────────────────────────

    /** @param array{id_punto_venta?:?int, precio:numeric, vigencia_desde:string, vigencia_hasta?:?string} $datos */
    public function agregarPrecio(int $idProducto, array $datos, int $idUser): array
    {
        return $this->operacion(function () use ($idProducto, $datos, $idUser) {
            // El bloqueo del producto ordena los precios simultáneos del mismo producto.
            $producto = Producto::lockForUpdate()->find($idProducto) ?? throw ReglaCafeteria::noExiste('El producto no existe');
            if (! $producto->activo || ! $producto->es_vendible) {
                throw new ReglaCafeteria('Solo los productos activos y vendibles llevan precio');
            }

            $idPunto = isset($datos['id_punto_venta']) ? (int) $datos['id_punto_venta'] : null;
            if ($idPunto !== null) {
                $punto = PuntoVenta::with('cafeteria:id,estado')->find($idPunto) ?? throw new ReglaCafeteria('El punto de venta no existe');
                if ($punto->cafeteria?->estado === EstadoCafeteria::CERRADA) {
                    throw new ReglaCafeteria('El punto de venta es de una cafetería cerrada');
                }
            }

            $desde = CarbonImmutable::parse($datos['vigencia_desde'])->startOfDay();
            $hasta = isset($datos['vigencia_hasta']) ? CarbonImmutable::parse($datos['vigencia_hasta'])->startOfDay() : null;
            if ($desde->lt(CarbonImmutable::today())) {
                throw new ReglaCafeteria('Un precio nuevo empieza hoy o después: el pasado no se cambia');
            }
            if ($hasta && $hasta->lt($desde)) {
                throw new ReglaCafeteria('La vigencia termina antes de empezar');
            }

            $cruzados = PrecioProducto::mismoAlcance($producto->id, $idPunto)
                ->where(fn ($q) => $q->whereNull('vigencia_hasta')->orWhere('vigencia_hasta', '>=', $desde->toDateString()))
                ->when($hasta, fn ($q) => $q->where('vigencia_desde', '<=', $hasta->toDateString()))
                ->get();

            foreach ($cruzados as $precio) {
                // El precio abierto que empezó antes se cierra el día anterior: el nuevo lo reemplaza.
                // Si el nuevo tiene fin (una promoción), el anterior vuelve a regir al día siguiente;
                // si no, el producto quedaría sin precio. Nada viene después de un precio abierto.
                if ($precio->vigencia_hasta === null && $precio->vigencia_desde->lt($desde)) {
                    $precio->update(['vigencia_hasta' => $desde->subDay()->toDateString()]);
                    if ($hasta) {
                        PrecioProducto::create([
                            'id_producto' => $producto->id,
                            'id_punto_venta' => $idPunto,
                            'precio' => $precio->precio,
                            'vigencia_desde' => $hasta->addDay()->toDateString(),
                            'vigencia_hasta' => null,
                            'creado_por' => $idUser,
                        ]);
                    }

                    continue;
                }

                throw new ReglaCafeteria(sprintf(
                    'Se cruza con el precio vigente del %s al %s',
                    $precio->vigencia_desde->format('Y-m-d'),
                    $precio->vigencia_hasta?->format('Y-m-d') ?? 'en adelante',
                ));
            }

            return PrecioProducto::create([
                'id_producto' => $producto->id,
                'id_punto_venta' => $idPunto,
                'precio' => $datos['precio'],
                'vigencia_desde' => $desde->toDateString(),
                'vigencia_hasta' => $hasta?->toDateString(),
                'creado_por' => $idUser,
            ]);
        }, 'Precio guardado');
    }

    /**
     * Solo un precio que aún no empieza (nunca se aplicó). El precio que terminaba justo el día
     * antes se alarga hasta donde llegaba el borrado: así cubre ese tramo sin dejar huecos ni
     * cruces. Si el borrado no tenía fin, el anterior vuelve a quedar abierto; si era una
     * promoción, el anterior llega hasta el fin de ella y su continuación sigue después.
     */
    public function eliminarPrecio(int $idPrecio): array
    {
        return $this->operacion(function () use ($idPrecio) {
            $precio = PrecioProducto::find($idPrecio) ?? throw ReglaCafeteria::noExiste('El precio no existe');
            Producto::lockForUpdate()->find($precio->id_producto);

            if ($precio->vigencia_desde->lte(CarbonImmutable::today())) {
                throw new ReglaCafeteria('Ese precio ya estuvo vigente: no se borra. Para cambiarlo, agrega uno nuevo');
            }

            $anterior = PrecioProducto::mismoAlcance($precio->id_producto, $precio->id_punto_venta)
                ->whereDate('vigencia_hasta', $precio->vigencia_desde->copy()->subDay())
                ->first();
            $anterior?->update(['vigencia_hasta' => $precio->vigencia_hasta?->format('Y-m-d')]);
            $precio->delete();

            return null;
        }, 'Precio eliminado');
    }

    /** El código lo pone el sistema: aunque llegue, no se cambia. */
    private static function sinCodigo(array $datos): array
    {
        unset($datos['codigo']);

        return $datos;
    }
}
