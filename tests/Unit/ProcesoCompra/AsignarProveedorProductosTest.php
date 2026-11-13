<?php

namespace Tests\Unit\ProcesoCompra;

use App\Http\Requests\ProcesoCompra\AsignarProveedorRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Productos editados en el estudio del seguimiento: descripción y cantidad obligatorias, y
 * líneas nuevas sin `id`. Solo valida `productos.*` (las reglas de proveedor tocan la BD).
 */
class AsignarProveedorProductosTest extends TestCase
{
    /** @param  array<int, array<string, mixed>>  $productos */
    private function errores(array $productos): array
    {
        $reglas = array_filter(
            (new AsignarProveedorRequest)->rules(),
            fn ($campo) => str_starts_with($campo, 'productos') && $campo !== 'productos.*.id',
            ARRAY_FILTER_USE_KEY,
        );

        return Validator::make(['productos' => $productos], $reglas)->errors()->keys();
    }

    public function test_una_linea_nueva_sin_id_es_valida(): void
    {
        $this->assertSame([], $this->errores([['producto' => 'Resma carta', 'cantidad' => '3', 'precio' => '12000', 'iva' => '19']]));
    }

    public function test_la_cantidad_debe_ser_entera_y_mayor_a_cero(): void
    {
        $this->assertContains('productos.0.cantidad', $this->errores([['producto' => 'Resma', 'cantidad' => '0']]));
        $this->assertContains('productos.0.cantidad', $this->errores([['producto' => 'Resma', 'cantidad' => '1.5']]));
    }

    public function test_la_descripcion_es_obligatoria(): void
    {
        $this->assertContains('productos.0.producto', $this->errores([['producto' => '', 'cantidad' => '2']]));
    }
}
