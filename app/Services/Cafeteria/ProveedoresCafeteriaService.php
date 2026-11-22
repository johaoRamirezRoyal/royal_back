<?php

namespace App\Services\Cafeteria;

use App\Enums\TipoPerfil;
use App\Models\Cafeteria\Producto;
use App\Models\Cafeteria\ProveedorProducto;
use App\Models\Usuarios\Perfil;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Proveedores y productos de la cafetería (opción 179). El proveedor es el de Compras
 * (decisión C1): un usuario activo de tipo PROVEEDOR, con nombre en `proveedor_detalle`. Aquí
 * solo se dice qué productos ofrece cada uno; su ficha se administra en Compras.
 * Un producto tiene a lo sumo un proveedor preferido (activo).
 */
class ProveedoresCafeteriaService
{
    use EjecutaOperacion;

    /** Proveedores activos con cuántos productos de la cafetería ofrecen. Busca por nombre o NIT. */
    public function proveedores(?string $busqueda): array
    {
        try {
            $s = trim((string) $busqueda);

            $proveedores = self::consulta()
                ->when($s !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('d.nombre', 'like', "%{$s}%")
                    ->orWhere('u.nombre', 'like', "%{$s}%")
                    ->orWhere('d.num_identificacion', 'like', "%{$s}%")
                    ->orWhere('u.documento', 'like', "%{$s}%")))
                ->addSelect(['productos_count' => ProveedorProducto::selectRaw('count(*)')
                    ->whereColumn('id_proveedor', 'u.id_user')
                    ->where('activo', true)])
                ->orderBy('nombre')
                ->limit(200)
                ->get();

            return ['error' => false, 'message' => 'Proveedores', 'data' => $proveedores];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function productosDeProveedor(int $idProveedor): array
    {
        try {
            $proveedor = self::consulta()->where('u.id_user', $idProveedor)->first();
            if (! $proveedor) {
                return ['error' => true, 'message' => 'El proveedor no existe o está inactivo', 'status' => 404];
            }

            $productos = ProveedorProducto::query()
                ->with(['producto:id,codigo,nombre,id_unidad_base,activo', 'producto.unidadBase:id,codigo,abreviatura,precision_decimal'])
                ->where('id_proveedor', $idProveedor)
                ->orderByDesc('activo')
                ->orderBy('id')
                ->get();

            return ['error' => false, 'message' => 'Productos del proveedor', 'data' => ['proveedor' => $proveedor, 'productos' => $productos]];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** @param array{id_proveedor:int, id_producto:int, codigo_producto_proveedor?:?string, precio_referencia?:?numeric, cantidad_minima?:?numeric, es_preferido?:bool} $datos */
    public function vincular(array $datos): array
    {
        return $this->operacion(function () use ($datos) {
            if (! self::consulta()->where('u.id_user', $datos['id_proveedor'])->exists()) {
                throw new ReglaCafeteria('El proveedor no existe o está inactivo');
            }

            $producto = Producto::lockForUpdate()->find($datos['id_producto']) ?? throw new ReglaCafeteria('El producto no existe');
            if (! $producto->activo) {
                throw new ReglaCafeteria('El producto está inactivo');
            }

            if (ProveedorProducto::where('id_proveedor', $datos['id_proveedor'])->where('id_producto', $producto->id)->exists()) {
                throw new ReglaCafeteria('El proveedor ya ofrece ese producto: edítalo en su lista');
            }

            $vinculo = ProveedorProducto::create([...$datos, 'activo' => true, 'es_preferido' => false]);
            if ($datos['es_preferido'] ?? false) {
                $this->marcarPreferido($vinculo);
            }

            return $vinculo->refresh();
        }, 'Producto agregado al proveedor');
    }

    public function actualizarVinculo(int $id, array $datos): array
    {
        return $this->operacion(function () use ($id, $datos) {
            $vinculo = ProveedorProducto::find($id) ?? throw ReglaCafeteria::noExiste('El vínculo no existe');
            Producto::lockForUpdate()->find($vinculo->id_producto);

            $preferido = $datos['es_preferido'] ?? $vinculo->es_preferido;
            unset($datos['id_proveedor'], $datos['id_producto'], $datos['es_preferido']);
            $vinculo->fill($datos);

            // Un vínculo inactivo no puede ser el preferido.
            if (! $vinculo->activo) {
                $preferido = false;
            }

            $vinculo->es_preferido = false;
            $vinculo->save();
            if ($preferido) {
                $this->marcarPreferido($vinculo);
            }

            return $vinculo->refresh();
        }, 'Vínculo actualizado');
    }

    /** Deja este vínculo como el único preferido de su producto. Llamar con el producto bloqueado. */
    private function marcarPreferido(ProveedorProducto $vinculo): void
    {
        ProveedorProducto::where('id_producto', $vinculo->id_producto)->where('id', '!=', $vinculo->id)->update(['es_preferido' => false]);
        $vinculo->update(['es_preferido' => true]);
    }

    /**
     * Nombre de cada proveedor por id (el de su ficha de Compras o, si no tiene, el del usuario).
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public static function nombres(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('usuarios as u')
            ->leftJoin('proveedor_detalle as d', 'd.id_proveedor', '=', 'u.id_user')
            ->whereIn('u.id_user', array_unique($ids))
            ->get(['u.id_user', DB::raw("COALESCE(NULLIF(d.nombre, ''), TRIM(CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, '')))) as nombre")])
            ->mapWithKeys(fn ($r) => [(int) $r->id_user => $r->nombre])
            ->all();
    }

    /** Proveedores activos: usuario de tipo PROVEEDOR con su ficha (si la tiene). */
    private static function consulta(): \Illuminate\Database\Query\Builder
    {
        return DB::table('usuarios as u')
            ->leftJoin('proveedor_detalle as d', 'd.id_proveedor', '=', 'u.id_user')
            ->where('u.estado', 'activo')
            ->whereIn('u.perfil', Perfil::idsDeTipo(TipoPerfil::PROVEEDOR))
            ->select([
                'u.id_user',
                DB::raw("COALESCE(NULLIF(d.nombre, ''), TRIM(CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, '')))) as nombre"),
                DB::raw('COALESCE(d.num_identificacion, u.documento) as identificacion'),
                DB::raw('COALESCE(d.correo, u.correo) as correo'),
                'd.telefono',
            ]);
    }
}
