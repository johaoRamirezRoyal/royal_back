<?php

namespace Tests\Unit\GestionAcademica;

use App\Mail\GenericMail;
use App\Models\GestionAcademica\ActaEstudiante;
use App\Services\GestionAcademica\ActasEstudianteService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Aviso al director de grupo al registrar actas (ActasEstudianteService::avisarDirectores):
 * un correo por director, nunca a quien la registró ni por un curso inactivo. El envío va
 * después de responder, así que el test termina la app para que corra.
 */
class AvisoDirectorActaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::connection()->getPdo()->sqliteCreateFunction('CONCAT', fn (...$partes) => implode('', $partes), -1);

        Schema::create('curso', function (Blueprint $t) {
            $t->increments('id');
            $t->string('nombre');
            $t->integer('id_director')->nullable();
            $t->integer('activo')->default(1);
        });
        Schema::create('usuarios', function (Blueprint $t) {
            $t->increments('id_user');
            $t->string('nombre');
            $t->string('apellido')->nullable();
            $t->string('correo')->nullable();
            $t->integer('id_curso')->nullable();
            $t->string('estado')->default('activo');
        });

        DB::table('curso')->insert([
            ['id' => 1, 'nombre' => 'Quinto A', 'id_director' => 10, 'activo' => 1],
            ['id' => 2, 'nombre' => 'Quinto B', 'id_director' => 11, 'activo' => 0], // inactivo
            ['id' => 3, 'nombre' => 'Sexto A', 'id_director' => null, 'activo' => 1], // sin director
        ]);
        DB::table('usuarios')->insert([
            ['id_user' => 10, 'nombre' => 'Dora', 'apellido' => null, 'correo' => 'dora@colegio.test', 'id_curso' => null],
            ['id_user' => 11, 'nombre' => 'Dario', 'apellido' => null, 'correo' => 'dario@colegio.test', 'id_curso' => null],
            ['id_user' => 1, 'nombre' => 'Ana', 'apellido' => '.', 'correo' => null, 'id_curso' => 1],
            ['id_user' => 2, 'nombre' => 'Beto', 'apellido' => null, 'correo' => null, 'id_curso' => 1],
            ['id_user' => 3, 'nombre' => 'Caro', 'apellido' => null, 'correo' => null, 'id_curso' => 2],
            ['id_user' => 4, 'nombre' => 'Dani', 'apellido' => null, 'correo' => null, 'id_curso' => 3],
        ]);

        Mail::fake();
    }

    private function avisar(array $idsEstudiantes, int $registra): void
    {
        $actas = collect($idsEstudiantes)->map(fn ($id) => new ActaEstudiante(['id_estudiante' => $id, 'tipo' => 'convivencial', 'fecha' => '2026-11-04']));
        $m = new ReflectionMethod(ActasEstudianteService::class, 'avisarDirectores');
        $m->setAccessible(true);
        $m->invoke(app(ActasEstudianteService::class), $actas, $registra);
        $this->app->terminate(); // corre lo despachado con afterResponse
    }

    public function test_un_correo_por_director_con_todas_las_actas_de_su_curso(): void
    {
        $this->avisar([1, 2, 3, 4], registra: 99);

        Mail::assertSent(GenericMail::class, 1);
        Mail::assertSent(GenericMail::class, fn (GenericMail $m) => $m->hasTo('dora@colegio.test')
            && str_contains($m->contenido, 'Ana: acta de convivencial')
            && str_contains($m->contenido, 'Beto: acta de convivencial')
            && ! str_contains($m->contenido, 'Caro'));
    }

    public function test_no_avisa_si_el_director_registro_el_acta(): void
    {
        $this->avisar([1], registra: 10);

        Mail::assertNothingSent();
    }
}
