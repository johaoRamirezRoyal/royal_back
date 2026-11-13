<?php

namespace Tests\Unit\GestionAcademica;

use App\Services\GestionAcademica\NotasService;
use PHPUnit\Framework\TestCase;

/**
 * Lo que guarda el boletín de un estudiante al cerrar (NotasService::fotoBoletin): solo
 * referentes con su desempeño final (ajuste ?? docente), agrupados por propósito, sin
 * subreferentes ni propósitos vacíos, y la observación general.
 */
class FotoBoletinTest extends TestCase
{
    public function test_foto(): void
    {
        $datos = [
            'propositos' => [
                ['id' => 1, 'nombre' => 'Comunicativo', 'descripcion' => null],
                ['id' => 2, 'nombre' => 'Vacío', 'descripcion' => null],
                ['id' => 3, 'nombre' => 'Ético', 'descripcion' => 'Valores'],
            ],
            'asignaturas' => [['id' => 10, 'nombre' => 'Español'], ['id' => 20, 'nombre' => 'Ética']],
            'referentes' => collect([
                ['id' => 100, 'id_proposito' => 1, 'id_asignatura' => 10, 'descripcion' => 'Lee', 'subreferentes' => [['id' => 101, 'descripcion' => 'Lee en voz alta']]],
                ['id' => 200, 'id_proposito' => 3, 'id_asignatura' => 20, 'descripcion' => 'Respeta'],
                ['id' => 300, 'id_proposito' => 3, 'id_asignatura' => 20, 'descripcion' => 'Sin nota'],
            ]),
        ];
        $estudiante = [
            'id_user' => 7, 'nombre' => 'Ana', 'apellido' => 'Pérez', 'documento' => '123', 'foto_carnet' => null, 'en_curso' => true,
            'notas' => collect([
                100 => ['docente' => 1, 'ajuste' => 2, 'final' => 2], // ajustado por el coordinador
                101 => ['docente' => 3, 'ajuste' => null, 'final' => 3], // subreferente: no sale
                200 => ['docente' => 1, 'ajuste' => null, 'final' => 1],
            ]),
            'observacion' => 'Muy bien',
        ];
        $escala = collect([
            1 => (object) ['nombre' => 'Superior', 'abreviatura' => 'S', 'color' => '#0a0'],
            2 => (object) ['nombre' => 'Alto', 'abreviatura' => 'A', 'color' => '#00a'],
            3 => (object) ['nombre' => 'Básico', 'abreviatura' => 'B', 'color' => '#aa0'],
        ]);

        $foto = NotasService::fotoBoletin(['curso' => 'Primero A'], $datos, $estudiante, $escala);

        $this->assertSame('Primero A', $foto['curso']);
        $this->assertSame(['id_user' => 7, 'nombre' => 'Ana', 'apellido' => 'Pérez', 'documento' => '123'], $foto['estudiante']);
        $this->assertSame(['Comunicativo', 'Ético'], array_column($foto['propositos'], 'nombre'));
        $this->assertSame([
            ['descripcion' => 'Lee', 'asignatura' => 'Español', 'desempeno' => ['nombre' => 'Alto', 'abreviatura' => 'A', 'color' => '#00a']],
        ], $foto['propositos'][0]['referentes']);
        $this->assertSame('Superior', $foto['propositos'][1]['referentes'][0]['desempeno']['nombre']);
        $this->assertNull($foto['propositos'][1]['referentes'][1]['desempeno']);
        $this->assertSame('Muy bien', $foto['observacion_general']);
    }
}
