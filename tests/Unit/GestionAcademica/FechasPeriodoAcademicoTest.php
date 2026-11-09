<?php

namespace Tests\Unit\GestionAcademica;

use App\Services\AnioEscolar\PeriodoAcademicoServices;
use PHPUnit\Framework\TestCase;

/**
 * Validación de fechas de un periodo académico (PeriodoAcademicoServices::errorDeFechas):
 * dentro del rango del año escolar según el calendario y sin cruzarse con los demás periodos.
 */
class FechasPeriodoAcademicoTest extends TestCase
{
    private const CAL_A = ['fecha_min' => '2026-02-01', 'fecha_max' => '2026-11-30'];
    private const CAL_B = ['fecha_min' => '2026-08-01', 'fecha_max' => '2027-06-30'];

    private function error(string $ini, string $fin, array $rango, array $otros = []): ?string
    {
        return PeriodoAcademicoServices::errorDeFechas($ini, $fin, $rango, $otros);
    }

    public function test_rango_del_calendario(): void
    {
        // Calendario A: un periodo que termina después de junio es válido (antes se rechazaba).
        $this->assertNull($this->error('2026-09-01', '2026-11-20', self::CAL_A));
        $this->assertNotNull($this->error('2026-10-01', '2026-12-05', self::CAL_A));
        $this->assertNotNull($this->error('2026-01-20', '2026-03-01', self::CAL_A));

        // Calendario B: cruza de año, hasta el 30 de junio del siguiente.
        $this->assertNull($this->error('2027-04-01', '2027-06-30', self::CAL_B));
        $this->assertNotNull($this->error('2027-05-01', '2027-07-15', self::CAL_B));

        // Fin no posterior al inicio.
        $this->assertNotNull($this->error('2026-03-10', '2026-03-10', self::CAL_A));
        // Acepta datetimes (como vienen de la BD).
        $this->assertNull($this->error('2026-02-01 00:00:00', '2026-04-30 00:00:00', self::CAL_A));
    }

    public function test_cruces(): void
    {
        $otros = [['nombre' => 'Primer periodo', 'fecha_inicio' => '2026-02-01', 'fecha_fin' => '2026-04-30']];

        $this->assertNull($this->error('2026-05-01', '2026-07-31', self::CAL_A, $otros));        // contiguo
        $this->assertStringContainsString('Primer periodo', (string) $this->error('2026-04-30', '2026-06-30', self::CAL_A, $otros)); // comparte el último día
        $this->assertNotNull($this->error('2026-03-01', '2026-03-15', self::CAL_A, $otros));     // contenido
        $this->assertNotNull($this->error('2026-02-01', '2026-11-30', self::CAL_A, $otros));     // lo contiene
    }
}
