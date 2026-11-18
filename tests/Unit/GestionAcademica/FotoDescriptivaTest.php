<?php

namespace Tests\Unit\GestionAcademica;

use App\Services\GestionAcademica\NotasService;
use PHPUnit\Framework\TestCase;

/**
 * Lo que guarda el boletín descriptivo de un estudiante al cerrar (NotasService::fotoDescriptiva):
 * por propósito, cada campo con su texto (sin texto = null, también si viene en blanco), los datos
 * del estudiante y la observación general.
 */
class FotoDescriptivaTest extends TestCase
{
    public function test_foto(): void
    {
        $datos = ['propositos' => collect([
            ['id' => 1, 'nombre' => 'Propósito 1', 'descripcion' => 'Identidad', 'campos' => [['id' => 10, 'nombre' => 'Fortalezas'], ['id' => 11, 'nombre' => 'Por fortalecer']]],
            ['id' => 2, 'nombre' => 'Propósito 2', 'descripcion' => null, 'campos' => [['id' => 20, 'nombre' => 'Recomendaciones']]],
        ])];
        $estudiante = [
            'id_user' => 7, 'nombre' => 'Ana', 'apellido' => 'Pérez', 'documento' => '123', 'usuario' => 'aperez', 'foto_carnet' => null, 'en_curso' => true,
            'numero_lista' => 3, 'inasistencias' => 1, 'llegadas_tarde' => 0,
            'textos' => collect([10 => ' Se adapta con alegría. ', 11 => '   ']),
            'observacion' => 'Buen inicio',
        ];

        $foto = NotasService::fotoDescriptiva(['tipo' => 'DESCRIPTIVA', 'curso' => 'Pre Kinder A'], $datos, $estudiante);

        $this->assertSame('DESCRIPTIVA', $foto['tipo']);
        $this->assertSame(['id_user' => 7, 'nombre' => 'Ana', 'apellido' => 'Pérez', 'documento' => '123', 'usuario' => 'aperez'], $foto['estudiante']);
        $this->assertSame([3, 1, 0], [$foto['numero_lista'], $foto['inasistencias'], $foto['llegadas_tarde']]);
        $this->assertSame([
            ['nombre' => 'Fortalezas', 'texto' => 'Se adapta con alegría.'],
            ['nombre' => 'Por fortalecer', 'texto' => null],
        ], $foto['propositos'][0]['campos']);
        $this->assertSame([['nombre' => 'Recomendaciones', 'texto' => null]], $foto['propositos'][1]['campos']);
        $this->assertSame('Buen inicio', $foto['observacion_general']);
    }
}
