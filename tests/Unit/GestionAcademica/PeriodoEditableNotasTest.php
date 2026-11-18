<?php

namespace Tests\Unit\GestionAcademica;

use App\Models\AnioEscolar\PeriodoAcademico;
use App\Services\GestionAcademica\NotasService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/** Ventana de notas: desde fecha_inicio hasta fecha_limite_notas (o fecha_fin si no hay). */
class PeriodoEditableNotasTest extends TestCase
{
    private function periodo(?string $limite): PeriodoAcademico
    {
        return (new PeriodoAcademico())->forceFill([
            'fecha_inicio' => '2026-02-01', 'fecha_fin' => '2026-04-30', 'fecha_limite_notas' => $limite,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
    }

    public function test_ventana(): void
    {
        $casos = [
            ['2026-01-31', null, false],         // antes de empezar
            ['2026-02-01', null, true],
            ['2026-04-30', null, true],          // último día sin plazo extra
            ['2026-05-01', null, false],
            ['2026-05-10', '2026-05-10', true],  // dentro del plazo de corrección
            ['2026-05-11', '2026-05-10', false],
        ];

        foreach ($casos as [$hoy, $limite, $esperado]) {
            Carbon::setTestNow($hoy);
            $this->assertSame($esperado, NotasService::periodoEditable($this->periodo($limite)), "hoy={$hoy} limite={$limite}");
        }
    }
}
