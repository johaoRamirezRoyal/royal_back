<?php

namespace Tests\Unit\Carnets;

use App\Services\Carnets\CarnetPlantillaService;
use PHPUnit\Framework\TestCase;

class CarnetPlantillaServiceTest extends TestCase
{
    public function test_normalizar_completa_y_acota_lo_que_manda_el_cliente(): void
    {
        $e = CarnetPlantillaService::normalizar([
            'nombre' => ['x' => 5, 'color' => 'rojo', 'fuente' => 'inexistente', 'prefijo' => str_repeat('a', 50), 'ajustar' => 1],
            'barras' => ['alto' => 9, 'color' => '#ABCDEF', 'visible' => false],
        ]);

        $this->assertSame(['nombre', 'detalle', 'documento', 'vigencia', 'foto', 'barras'], array_keys($e));
        $this->assertSame(1.0, $e['nombre']['x']);
        $this->assertSame('#ffffff', $e['nombre']['color']);
        $this->assertSame('poppins', $e['nombre']['fuente']);
        $this->assertSame(30, mb_strlen($e['nombre']['prefijo']));
        $this->assertSame('Vigencia ', $e['vigencia']['prefijo']);
        $this->assertSame(0.6, $e['barras']['alto']);
        $this->assertSame('#abcdef', $e['barras']['color']);
        $this->assertFalse($e['barras']['visible']);
        $this->assertTrue($e['detalle']['visible']);
        $this->assertTrue($e['nombre']['ajustar']);
        $this->assertFalse($e['detalle']['ajustar']);
    }

    public function test_proporcion_del_carnet_vertical(): void
    {
        $this->assertTrue(CarnetPlantillaService::proporcionValida(638, 1011));
        $this->assertTrue(CarnetPlantillaService::proporcionValida(1276, 2022));
        $this->assertFalse(CarnetPlantillaService::proporcionValida(1011, 638));
        $this->assertFalse(CarnetPlantillaService::proporcionValida(1000, 1000));
    }
}
