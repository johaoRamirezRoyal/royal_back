<?php

namespace Tests\Unit\GestionAcademica;

use App\Services\GestionAcademica\NotasService;
use PHPUnit\Framework\TestCase;

/**
 * Qué hace guardarReferentes con la lista enviada (NotasService::aplanar y cambiosDeReferentes):
 * varios docentes editan el mismo plan, cada uno en sus asignaturas, sin pisarse.
 */
class CambiosDeReferentesTest extends TestCase
{
    // Existentes: 1 y 2 en Matemáticas (10), 2 subreferente de 1; 3 en Inglés (20). Propósito 5.
    private const EXISTENTES = [
        1 => ['id_proposito' => 5, 'id_asignatura' => 10, 'id_padre' => null, 'descripcion' => 'Suma'],
        2 => ['id_proposito' => 5, 'id_asignatura' => 10, 'id_padre' => 1, 'descripcion' => 'Suma de dos cifras'],
        3 => ['id_proposito' => 5, 'id_asignatura' => 20, 'id_padre' => null, 'descripcion' => 'Saludos'],
    ];

    private const MATE = ['id' => 1, 'id_proposito' => 5, 'id_asignatura' => 10, 'descripcion' => 'Suma', 'subreferentes' => [['id' => 2, 'descripcion' => 'Suma de dos cifras']]];
    private const INGLES = ['id' => 3, 'id_proposito' => 5, 'id_asignatura' => 20, 'descripcion' => 'Saludos'];

    private function cambios(array $referentes, array $editables): array
    {
        return NotasService::cambiosDeReferentes(self::EXISTENTES, NotasService::aplanar($referentes), $editables);
    }

    public function test_aplanar_hereda_proposito_y_asignatura(): void
    {
        $planos = NotasService::aplanar([
            ['id_proposito' => 5, 'id_asignatura' => 10, 'descripcion' => ' Resta ', 'subreferentes' => [['descripcion' => 'Resta llevando']]],
            self::INGLES,
        ]);

        $this->assertCount(3, $planos);
        $this->assertSame(['id' => null, 'id_proposito' => 5, 'id_asignatura' => 10, 'id_padre' => null, 'padre' => null, 'descripcion' => 'Resta'], $planos[0]);
        $this->assertSame(['id' => null, 'id_proposito' => 5, 'id_asignatura' => 10, 'id_padre' => null, 'padre' => 0, 'descripcion' => 'Resta llevando'], $planos[1]);
        $this->assertSame(3, $planos[2]['id']);
    }

    public function test_quien_edita_todo_reemplaza_la_lista(): void
    {
        // Quitar el referente 1 quita también su subreferente 2 (no viene anidado).
        $r = $this->cambios([self::INGLES], [10, 20]);
        $this->assertNull($r['rechazada']);
        $this->assertSame([1, 2], $r['eliminar']);
    }

    public function test_docente_conserva_lo_de_los_demas(): void
    {
        // El de Matemáticas manda solo lo suyo, con un subreferente nuevo: Inglés no se elimina.
        $mate = self::MATE;
        $mate['subreferentes'][] = ['descripcion' => 'Suma llevando'];
        $r = $this->cambios([$mate], [10]);
        $this->assertNull($r['rechazada']);
        $this->assertSame([], $r['eliminar']);
        $this->assertSame([], $r['ignorar']);
    }

    public function test_lo_ajeno_sin_cambios_se_ignora(): void
    {
        $ingles = self::INGLES;
        $ingles['descripcion'] = ' Saludos ';
        $r = $this->cambios([self::MATE, $ingles], [10]);
        $this->assertNull($r['rechazada']);
        $this->assertSame([2], $r['ignorar']); // índice de Inglés en la lista aplanada
        $this->assertSame([], $r['eliminar']);
    }

    public function test_rechazos_en_asignatura_ajena(): void
    {
        $casos = [
            'cambiar' => ['descripcion' => 'Despedidas'] + self::INGLES,
            'crear' => ['id_proposito' => 5, 'id_asignatura' => 20, 'descripcion' => 'Colores'],
            'mover hacia' => ['id_asignatura' => 20] + self::MATE,
            'mover desde' => ['id_asignatura' => 10] + self::INGLES,
            'otro propósito' => ['id_proposito' => 6] + self::INGLES,
            'subreferente nuevo' => self::INGLES + ['subreferentes' => [['descripcion' => 'Hello']]],
        ];

        foreach ($casos as $caso => $r) {
            $lista = $caso === 'mover hacia' ? [$r, self::INGLES] : [self::MATE, $r];
            $this->assertSame(20, $this->cambios($lista, [10])['rechazada'], $caso);
        }
    }

    public function test_id_ajeno_se_ignora(): void
    {
        $r = $this->cambios([['id' => 99, 'id_proposito' => 5, 'id_asignatura' => 10, 'descripcion' => 'X']], [10, 20]);
        $this->assertNull($r['rechazada']);
        $this->assertSame([0], $r['ignorar']);
        $this->assertSame([1, 2, 3], $r['eliminar']);
    }
}
