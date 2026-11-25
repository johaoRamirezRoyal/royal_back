<?php

namespace Tests\Feature\LlegadasTarde;

use App\Http\Controllers\LlegadasTarde\LlegadasTardeController;
use App\Models\Usuarios\Perfil;
use App\Models\Usuarios\Usuario;
use App\Services\LlegadasTardeEstudiantes\LlegadasTarde;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * El listado del período muestra la llegada tarde más reciente de cada alumno, con su conteo
 * del período (sin revocadas ni justificadas), y el Docente sin la opción 99 ve ese mismo
 * listado de todo el colegio. Tablas mínimas en sqlite en memoria; nunca toca una base real.
 */
class ListadoPeriodoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', DB::getDriverName(), 'Corre los tests con `composer run test` (limpia la caché de config).');

        Schema::create('usuarios', function (Blueprint $t) {
            $t->increments('id_user');
            $t->string('nombre')->nullable();
            $t->string('apellido')->nullable();
            $t->string('correo')->nullable();
            $t->integer('id_curso')->nullable();
            $t->integer('perfil')->nullable();
        });
        Schema::create('curso', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nombre');
        });
        Schema::create('periodo_academico', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nombre');
            $t->date('fecha_inicio');
            $t->date('fecha_fin');
            $t->boolean('activo')->default(true);
        });
        Schema::create('llegadas_tardes', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('id_alumno');
            $t->integer('id_periodo_academico');
            $t->date('fecha');
            $t->time('hora');
            $t->text('observacion')->nullable();
            $t->boolean('revocado')->default(false);
            $t->boolean('justificada')->default(false);
        });

        DB::table('curso')->insert([['id' => 1, 'nombre' => '5A'], ['id' => 2, 'nombre' => '9B']]);
        DB::table('usuarios')->insert([
            ['id_user' => 10, 'nombre' => 'Ana', 'id_curso' => 1],
            ['id_user' => 20, 'nombre' => 'Luis', 'id_curso' => 2],
        ]);
        DB::table('periodo_academico')->insert([
            ['id' => 1, 'nombre' => 'Primer periodo', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-06-30'],
            ['id' => 2, 'nombre' => 'Segundo periodo', 'fecha_inicio' => '2026-07-01', 'fecha_fin' => '2026-12-31'],
        ]);
        DB::table('llegadas_tardes')->insert([
            ['id_alumno' => 10, 'id_periodo_academico' => 2, 'fecha' => '2026-11-03', 'hora' => '07:20:00', 'revocado' => false, 'justificada' => false],
            ['id_alumno' => 10, 'id_periodo_academico' => 2, 'fecha' => '2026-11-10', 'hora' => '07:25:00', 'revocado' => false, 'justificada' => false],
            ['id_alumno' => 10, 'id_periodo_academico' => 2, 'fecha' => '2026-11-12', 'hora' => '07:30:00', 'revocado' => true, 'justificada' => false],
            ['id_alumno' => 20, 'id_periodo_academico' => 2, 'fecha' => '2026-11-05', 'hora' => '07:18:00', 'revocado' => false, 'justificada' => false],
            // Otro período: no debe salir.
            ['id_alumno' => 10, 'id_periodo_academico' => 1, 'fecha' => '2026-03-02', 'hora' => '07:40:00', 'revocado' => false, 'justificada' => false],
        ]);
    }

    /** [id_alumno, fecha, total] de cada fila, en el orden de la respuesta. */
    private function resumen(iterable $registros): array
    {
        return collect($registros)
            ->map(fn ($r) => [(int) $r['id_alumno'], substr((string) $r['fecha'], 0, 10), (int) $r['total_llegadas_tarde_periodo']])
            ->all();
    }

    public function test_muestra_la_mas_reciente_de_cada_alumno_con_su_conteo(): void
    {
        $res = app(LlegadasTarde::class)->obtenerLlegadasTarde(2);

        $this->assertFalse($res['error']);
        // Ana: la más reciente es la revocada del 12; cuenta 2 (la revocada no suma).
        $this->assertSame(
            [[10, '2026-11-12', 2], [20, '2026-11-05', 1]],
            $this->resumen(collect($res['data']['registros'])->map->toArray()),
        );
    }

    public function test_el_docente_sin_opcion_99_ve_las_llegadas_de_todo_el_colegio(): void
    {
        $docente = new Usuario(['nombre' => 'Profe']);
        $docente->id_user = 99;
        $docente->setRelation('perfilRelacion', new Perfil(['tipo' => 'DOCENTE']));

        $request = Request::create('/api/llegadas-tarde', 'GET', ['id_periodo_academico' => 2]);
        $request->setUserResolver(fn () => $docente);
        $request->attributes->set('permisos_perfil', []); // sin la 99: rama de autoservicio del Docente

        $json = app(LlegadasTardeController::class)->obtenerLlegadasTarde($request)->getData(true);

        $this->assertFalse($json['error']);
        // Alumnos de cursos distintos (no solo los del docente), una fila por alumno con su conteo.
        $this->assertSame([[10, '2026-11-12', 2], [20, '2026-11-05', 1]], $this->resumen($json['data']['registros']));
    }
}
