<?php

namespace Tests\Unit\Carnets;

use App\Services\Carnets\CarnetService;
use PHPUnit\Framework\TestCase;

class CarnetServiceTest extends TestCase
{
    public function test_transiciones_de_estado(): void
    {
        $this->assertTrue(CarnetService::transicionPermitida('IMPRESO', 'ENTREGADO'));
        $this->assertTrue(CarnetService::transicionPermitida('REIMPRESO', 'ENTREGADO'));
        $this->assertTrue(CarnetService::transicionPermitida('REIMPRESO', 'PERDIDO'));
        $this->assertFalse(CarnetService::transicionPermitida('ENTREGADO', 'REIMPRESO'));
        $this->assertTrue(CarnetService::transicionPermitida('ENTREGADO', 'PERDIDO'));
        $this->assertFalse(CarnetService::transicionPermitida('ENTREGADO', 'ENTREGADO'));
        $this->assertFalse(CarnetService::transicionPermitida('PERDIDO', 'ENTREGADO'));
        $this->assertFalse(CarnetService::transicionPermitida('ANULADO', 'IMPRESO'));
    }

    public function test_documento_desde_el_nombre_del_archivo(): void
    {
        $this->assertSame('1234567', CarnetService::documentoDeArchivo('1234567.jpg'));
        $this->assertSame('1234567', CarnetService::documentoDeArchivo('1.234.567 .JPG'));
        $this->assertSame('TI1020', CarnetService::documentoDeArchivo('TI-1020.png'));
    }
}
