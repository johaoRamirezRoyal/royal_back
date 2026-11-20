<?php

namespace Tests\Unit\GestionAcademica;

use App\Models\Usuarios\Nivel;
use App\Services\GestionAcademica\DocenteHorarioService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Cubre idsNivelAcademicoParaNivel(): los niveles académicos que abarca un nivel de usuario, según
 * nivel_nivel_academico (N:M desde 2026-11-20; antes, nivel.id_nivel_academico + Secundaria → 3
 * fijo en código). La fusión id_nivel + id_nivel_2 del docente se ejerce vía verMenu().
 */
class DocenteHorarioServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('nivel_nivel_academico', function (Blueprint $table) {
            $table->integer('id_nivel');
            $table->integer('id_nivel_academico');
            $table->primary(['id_nivel', 'id_nivel_academico']);
        });
        DB::table('nivel_nivel_academico')->insert([
            ['id_nivel' => 2, 'id_nivel_academico' => 1], // Preescolar → Educación preescolar
            ['id_nivel' => 2, 'id_nivel_academico' => 5], // Preescolar → Prejardín
            ['id_nivel' => 3, 'id_nivel_academico' => 2], // Primaria
            ['id_nivel' => 4, 'id_nivel_academico' => 3], // Secundaria → Básica secundaria
            ['id_nivel' => 4, 'id_nivel_academico' => 4], // Secundaria → Media
        ]);
    }

    private function ids(?Nivel $nivel): array
    {
        $ids = DocenteHorarioService::idsNivelAcademicoParaNivel($nivel);
        sort($ids);

        return $ids;
    }

    private static function nivel(int $id): Nivel
    {
        return (new Nivel)->forceFill(['id' => $id]);
    }

    public function test_nivel_nulo_no_aporta_ids(): void
    {
        $this->assertSame([], $this->ids(null));
    }

    public function test_nivel_sin_niveles_academicos_no_aporta_ids(): void
    {
        $this->assertSame([], $this->ids(self::nivel(8)));
    }

    public function test_nivel_con_uno_aporta_ese(): void
    {
        $this->assertSame([2], $this->ids(self::nivel(3)));
    }

    public function test_nivel_con_varios_aporta_todos(): void
    {
        $this->assertSame([1, 5], $this->ids(self::nivel(2)));
        $this->assertSame([3, 4], $this->ids(self::nivel(4)));
    }
}
