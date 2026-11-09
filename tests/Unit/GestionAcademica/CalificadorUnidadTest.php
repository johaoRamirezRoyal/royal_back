<?php

namespace Tests\Unit\GestionAcademica;

use App\Models\GestionAcademica\Dimension;
use App\Services\GestionAcademica\NotasService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Quién califica una unidad evaluable según `nivel_academico.calificador`
 * (NotasService::calificadorPermite) y la consistencia de tipo de una unidad (Dimension::errorDeTipo).
 */
class CalificadorUnidadTest extends TestCase
{
    public static function casos(): array
    {
        return [
            'director: el director califica' => ['DIRECTOR_GRUPO', true, false, true],
            'director: con carga no basta' => ['DIRECTOR_GRUPO', false, true, false],
            'docente de asignatura: con carga califica' => ['DOCENTE_ASIGNATURA', false, true, true],
            'docente de asignatura: el director sin carga no' => ['DOCENTE_ASIGNATURA', true, false, false],
            'sin calificador: el director' => [null, true, false, true],
            'sin calificador: con carga no basta' => [null, false, true, false],
            'calificador desconocido: el director' => ['OTRO', true, false, true],
        ];
    }

    #[DataProvider('casos')]
    public function test_calificador_permite(?string $calificador, bool $dirige, bool $tieneCarga, bool $esperado): void
    {
        $this->assertSame($esperado, NotasService::calificadorPermite($calificador, $dirige, $tieneCarga));
    }

    public function test_tipo_de_unidad_consistente(): void
    {
        $this->assertNull(Dimension::errorDeTipo('DIMENSION', null, null));
        $this->assertNull(Dimension::errorDeTipo('AREA', 3, null));
        $this->assertNull(Dimension::errorDeTipo('ASIGNATURA', null, 7));

        $this->assertNotNull(Dimension::errorDeTipo('DIMENSION', 3, null));
        $this->assertNotNull(Dimension::errorDeTipo('AREA', null, null));
        $this->assertNotNull(Dimension::errorDeTipo('AREA', 3, 7));
        $this->assertNotNull(Dimension::errorDeTipo('ASIGNATURA', null, null));
        $this->assertNotNull(Dimension::errorDeTipo('OTRO', null, null));
        $this->assertNotNull(Dimension::errorDeTipo(null, null, null));
    }
}
