<?php

namespace Tests\Unit\GestionAcademica;

use App\Services\GestionAcademica\NotasService;
use PHPUnit\Framework\TestCase;

/**
 * Qué hace guardarLogros con la lista enviada según las unidades que quien guarda puede editar
 * (NotasService::cambiosDeLogros): varios docentes editan el mismo plan sin pisarse.
 */
class CambiosDeLogrosTest extends TestCase
{
    // Logros existentes: 1 y 2 en la unidad 10 (Matemáticas), 3 en la 20 (Inglés).
    private const EXISTENTES = [
        1 => ['id_dimension' => 10, 'descripcion' => 'Suma'],
        2 => ['id_dimension' => 10, 'descripcion' => 'Resta'],
        3 => ['id_dimension' => 20, 'descripcion' => 'Saludos'],
    ];

    private function cambios(array $enviados, array $editables): array
    {
        return NotasService::cambiosDeLogros(self::EXISTENTES, $enviados, $editables);
    }

    public function test_quien_edita_todo_reemplaza_la_lista(): void
    {
        // Como en modo DIMENSION: lo que no viene se elimina.
        $r = $this->cambios([['id' => 1, 'id_dimension' => 10, 'descripcion' => 'Suma']], [10, 20]);
        $this->assertNull($r['rechazada']);
        $this->assertSame([2, 3], $r['eliminar']);
        $this->assertSame([], $r['ignorar']);
    }

    public function test_docente_conserva_lo_de_los_demas(): void
    {
        // El de Matemáticas manda solo lo suyo: el logro de Inglés no se elimina.
        $r = $this->cambios([
            ['id' => 1, 'id_dimension' => 10, 'descripcion' => 'Suma de dos cifras'],
            ['id_dimension' => 10, 'descripcion' => 'Multiplica'],
        ], [10]);
        $this->assertNull($r['rechazada']);
        $this->assertSame([2], $r['eliminar']);
    }

    public function test_lo_ajeno_sin_cambios_se_ignora(): void
    {
        // El frontend manda la lista completa: el de Inglés viene igual (con espacios de más) y se ignora.
        $r = $this->cambios([
            ['id' => 1, 'id_dimension' => 10, 'descripcion' => 'Suma'],
            ['id' => 2, 'id_dimension' => 10, 'descripcion' => 'Resta'],
            ['id' => 3, 'id_dimension' => 20, 'descripcion' => ' Saludos '],
        ], [10]);
        $this->assertNull($r['rechazada']);
        $this->assertSame([2], $r['ignorar']);
        $this->assertSame([], $r['eliminar']);
    }

    public function test_rechazos_en_unidad_ajena(): void
    {
        $this->assertSame(20, $this->cambios([['id' => 3, 'id_dimension' => 20, 'descripcion' => 'Despedidas']], [10])['rechazada']); // cambiar
        $this->assertSame(20, $this->cambios([['id_dimension' => 20, 'descripcion' => 'Colores']], [10])['rechazada']);                // crear
        $this->assertSame(20, $this->cambios([['id' => 1, 'id_dimension' => 20, 'descripcion' => 'Suma']], [10])['rechazada']);       // mover hacia
        $this->assertSame(20, $this->cambios([['id' => 3, 'id_dimension' => 10, 'descripcion' => 'Saludos']], [10])['rechazada']);    // mover desde
    }

    public function test_id_ajeno_se_ignora(): void
    {
        $r = $this->cambios([['id' => 99, 'id_dimension' => 10, 'descripcion' => 'X']], [10, 20]);
        $this->assertNull($r['rechazada']);
        $this->assertSame([0], $r['ignorar']);
        $this->assertSame([1, 2, 3], $r['eliminar']);
    }
}
