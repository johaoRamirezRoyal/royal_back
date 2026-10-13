<?php

namespace Tests\Unit\Hikvision;

use App\Services\Hikvisionattendance\hikvisionattendanceService;
use Tests\TestCase;

class SanitizarNombreHikvisionTest extends TestCase
{
    public function test_colapsa_espacios_multiples(): void
    {
        $this->assertSame('VICTORIA MOSCOSO GLEN', hikvisionattendanceService::sanitizarNombre("VICTORIA MOSCOSO       GLEN"));
        $this->assertSame('ANA MARIA', hikvisionattendanceService::sanitizarNombre("ANA\t\r\nMARIA"));
    }

    public function test_quita_espacios_en_los_bordes(): void
    {
        $this->assertSame('VICTORIA MOSCOSO GLEN', hikvisionattendanceService::sanitizarNombre('  VICTORIA MOSCOSO GLEN '));
    }

    public function test_limita_a_32_caracteres_sin_espacio_final(): void
    {
        $nombre = hikvisionattendanceService::sanitizarNombre('MARIA FERNANDA DE LOS ANGELES RODRIGUEZ GOMEZ');

        $this->assertSame('MARIA FERNANDA DE LOS ANGELES RO', $nombre);
        $this->assertLessThanOrEqual(32, strlen($nombre));

        // El corte cae justo después de un espacio: no debe quedar espacio al final.
        $this->assertSame('ABCDEFGHIJKLMNOPQRSTUVWXYZABCDE', hikvisionattendanceService::sanitizarNombre('ABCDEFGHIJKLMNOPQRSTUVWXYZABCDE FIN'));
    }

    public function test_translitera_tildes_y_enie(): void
    {
        $this->assertSame('Jose Perez Nunez', hikvisionattendanceService::sanitizarNombre('José Pérez Núñez'));
        $this->assertSame('MUNOZ ANGEL', hikvisionattendanceService::sanitizarNombre('MUÑOZ ÁNGEL'));
    }
}
