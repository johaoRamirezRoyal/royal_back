<?php

namespace Tests\Unit\Cursos;

use App\Services\AnioEscolar\AnioEscolarServices;
use App\Services\Cursos\CursosServices;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Sugerencias del director de grupo ("Mi curso"): reemplazan su lista del año que se cierra,
 * ignoran estudiantes de otros cursos y la promoción las ve como `sugerido_repetir`.
 * sqlite en memoria; el año lo da un AnioEscolarServices de prueba.
 */
class SugerenciasPromocionTest extends TestCase
{
    private const DIRECTOR = 50;

    private const ANIO = 9;

    private CursosServices $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('curso', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nombre');
            $t->integer('curso_proximo')->nullable();
            $t->integer('id_director')->nullable();
            $t->integer('activo')->default(1);
        });
        Schema::create('usuarios', function (Blueprint $t) {
            $t->increments('id_user');
            $t->string('nombre');
            $t->string('apellido')->nullable();
            $t->string('documento')->nullable();
            $t->integer('id_curso')->nullable();
            $t->integer('perfil');
            $t->string('estado')->default('activo');
        });
        (require database_path('migrations/2026_11_04_100000_create_promocion_sugerencias_table.php'))->up();

        DB::table('curso')->insert([
            ['id' => 1, 'nombre' => 'Quinto A', 'curso_proximo' => null, 'id_director' => self::DIRECTOR, 'activo' => 1],
            ['id' => 2, 'nombre' => 'Quinto B', 'curso_proximo' => null, 'id_director' => null, 'activo' => 1],
        ]);
        DB::table('usuarios')->insert([
            ['id_user' => 1, 'nombre' => 'Ana', 'id_curso' => 1, 'perfil' => 16],
            ['id_user' => 2, 'nombre' => 'Beto', 'id_curso' => 1, 'perfil' => 16],
            ['id_user' => 3, 'nombre' => 'Caro', 'id_curso' => 2, 'perfil' => 16], // de otro curso
        ]);

        $anios = $this->createMock(AnioEscolarServices::class);
        $anios->method('estadoCierreAnio')->willReturn(null);
        $anios->method('obtenerUltimoAnioEscolar')->willReturn(['error' => false, 'data' => (object) ['id' => self::ANIO]]);
        $this->servicio = new CursosServices($anios);
    }

    private function sugeridos(): array
    {
        return DB::table('promocion_sugerencias')->where('id_anio_escolar', self::ANIO)->orderBy('id_estudiante')->pluck('id_estudiante')->all();
    }

    public function test_guarda_solo_estudiantes_de_su_curso(): void
    {
        $r = $this->servicio->sugerirRepiten(self::DIRECTOR, [1, 3]);

        $this->assertFalse($r['error']);
        $this->assertSame([1], $this->sugeridos());
    }

    public function test_cada_guardado_reemplaza_la_lista_anterior(): void
    {
        $this->servicio->sugerirRepiten(self::DIRECTOR, [1, 2]);
        $this->servicio->sugerirRepiten(self::DIRECTOR, [2]);
        $this->assertSame([2], $this->sugeridos());

        $this->servicio->sugerirRepiten(self::DIRECTOR, []);
        $this->assertSame([], $this->sugeridos());
    }

    public function test_quien_no_dirige_no_puede_sugerir(): void
    {
        $r = $this->servicio->sugerirRepiten(99, [1]);

        $this->assertTrue($r['error']);
        $this->assertSame([], $this->sugeridos());
    }

    public function test_la_promocion_ve_la_sugerencia(): void
    {
        $this->servicio->sugerirRepiten(self::DIRECTOR, [2]);

        $marcas = collect($this->servicio->estudiantesParaPromocion([1, 2])['data'])
            ->mapWithKeys(fn ($e) => [$e->id_user => (bool) $e->sugerido_repetir])->all();

        $this->assertSame([1 => false, 2 => true, 3 => false], $marcas);
    }
}
