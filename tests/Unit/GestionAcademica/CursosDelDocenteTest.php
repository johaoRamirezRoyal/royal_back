<?php

namespace Tests\Unit\GestionAcademica;

use App\Services\GestionAcademica\CargaAcademicaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * CargaAcademicaService::obtenerCursosDocente es la única fuente de "los cursos del docente"
 * (Observaciones, Actas, Métricas de asistencia y Mis llegadas tarde). Tablas legacy creadas a
 * mano en sqlite en memoria, solo con las columnas que usa la consulta.
 */
class CursosDelDocenteTest extends TestCase
{
    private const DOCENTE = 7;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('curso', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nombre');
            $t->integer('id_director')->nullable();
            $t->integer('activo')->default(1);
        });
        Schema::create('academico_docente_asignatura', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('id_docente');
        });
        Schema::create('academico_carga_academica', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('id_curso');
            $t->integer('id_docente_asignatura')->nullable();
            $t->integer('id_docente')->nullable();
            $t->integer('activo')->default(1);
        });

        DB::table('curso')->insert([
            ['id' => 1, 'nombre' => 'Quinto A', 'id_director' => null, 'activo' => 1],   // carga por asignatura
            ['id' => 2, 'nombre' => 'Cuarto B', 'id_director' => null, 'activo' => 1],   // carga suelta
            ['id' => 3, 'nombre' => 'Sexto A', 'id_director' => self::DOCENTE, 'activo' => 1], // solo dirige
            ['id' => 4, 'nombre' => 'Primero A', 'id_director' => self::DOCENTE, 'activo' => 1], // dirige y dicta
            ['id' => 5, 'nombre' => 'Noveno C', 'id_director' => self::DOCENTE, 'activo' => 0], // dirigido pero inactivo
            ['id' => 6, 'nombre' => 'Octavo A', 'id_director' => null, 'activo' => 1],   // carga inactiva
        ]);
        DB::table('academico_docente_asignatura')->insert([['id' => 10, 'id_docente' => self::DOCENTE]]);
        DB::table('academico_carga_academica')->insert([
            ['id_curso' => 1, 'id_docente_asignatura' => 10, 'id_docente' => null, 'activo' => 1],
            ['id_curso' => 2, 'id_docente_asignatura' => null, 'id_docente' => self::DOCENTE, 'activo' => 1],
            ['id_curso' => 4, 'id_docente_asignatura' => 10, 'id_docente' => null, 'activo' => 1],
            ['id_curso' => 6, 'id_docente_asignatura' => 10, 'id_docente' => null, 'activo' => 0],
        ]);
    }

    private function cursos(int $idDocente): array
    {
        $resultado = (new CargaAcademicaService())->obtenerCursosDocente($idDocente);
        $this->assertFalse($resultado['error']);

        return collect($resultado['data'])->map(fn ($c) => (int) $c->id)->all();
    }

    public function test_incluye_carga_por_asignatura_carga_suelta_y_el_curso_que_dirige(): void
    {
        // Ordenados por nombre; Primero A una sola vez aunque lo dirige y además le dicta clase.
        $this->assertSame([2, 4, 1, 3], $this->cursos(self::DOCENTE));
    }

    public function test_sin_carga_ni_direccion_no_tiene_cursos(): void
    {
        $this->assertSame([], $this->cursos(99));
    }
}
