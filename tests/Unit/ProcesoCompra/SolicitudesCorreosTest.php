<?php

namespace Tests\Unit\ProcesoCompra;

use App\Services\ProcesoCompra\SolicitudesServices;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Los avisos de solicitudes de compra suman el grupo COMPRAS_VENTAS de
 * `correos_institucionales`. La tabla se crea a mano: los tests no corren migraciones.
 */
class SolicitudesCorreosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('correos_institucionales', function (Blueprint $table) {
            $table->id();
            $table->string('grupo');
            $table->string('nombre')->nullable();
            $table->string('correo');
            $table->boolean('activo')->default(true);
        });

        DB::table('correos_institucionales')->insert([
            ['grupo' => 'COMPRAS_VENTAS', 'correo' => 'compras@ejemplo.test', 'activo' => 1],
            ['grupo' => 'COMPRAS_VENTAS', 'correo' => 'coord@ejemplo.test', 'activo' => 1],
            ['grupo' => 'COMPRAS_VENTAS', 'correo' => 'inactivo@ejemplo.test', 'activo' => 0],
            ['grupo' => 'OTRO', 'correo' => 'otro@ejemplo.test', 'activo' => 1],
        ]);
    }

    public function test_suma_compras_ventas_activos_sin_repetidos(): void
    {
        $metodo = new ReflectionMethod(SolicitudesServices::class, 'conComprasVentas');
        $correos = $metodo->invoke(app(SolicitudesServices::class), ['coord@ejemplo.test']);

        $this->assertSame(['coord@ejemplo.test', 'compras@ejemplo.test'], $correos);
    }
}
