<?php

namespace Tests\Unit\Carnets;

use App\Services\Carnets\CarnetService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Solicitudes de carnet (CarnetService::solicitar / responderSolicitud / seguimiento): solo para
 * quien lleva carnet y ya tiene impreso el primero, una pendiente por persona, responder una sola
 * vez y el seguimiento de la más reciente a la más antigua. sqlite en memoria.
 */
class SolicitudesCarnetTest extends TestCase
{
    private CarnetService $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        DB::connection()->getPdo()->sqliteCreateFunction('CONCAT', fn (...$partes) => implode('', $partes), -1);

        Schema::create('usuarios', function (Blueprint $t) {
            $t->increments('id_user');
            $t->string('nombre');
            $t->string('apellido')->nullable();
            $t->integer('perfil');
            $t->string('estado')->default('activo');
        });
        Schema::create('carnets', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('id_user');
            $t->string('estado');
        });
        (require database_path('migrations/2026_11_04_120000_create_carnet_solicitudes_table.php'))->up();
        // Quién lleva carnet se decide por perfiles.tipo, no por el número de perfil.
        Schema::create('perfiles', function (Blueprint $t) {
            $t->integer('id_perfil');
            $t->string('tipo');
        });
        DB::table('perfiles')->insert([
            ['id_perfil' => 16, 'tipo' => 'ESTUDIANTE'],
            ['id_perfil' => 6, 'tipo' => 'ACUDIENTE'],
        ]);

        DB::table('usuarios')->insert([
            ['id_user' => 1, 'nombre' => 'Ana', 'apellido' => null, 'perfil' => 16, 'estado' => 'activo'],
            ['id_user' => 2, 'nombre' => 'Acudiente', 'apellido' => null, 'perfil' => 6, 'estado' => 'activo'], // no lleva carnet
            ['id_user' => 3, 'nombre' => 'Beto', 'apellido' => null, 'perfil' => 16, 'estado' => 'activo'], // aún sin carnet impreso
        ]);
        DB::table('carnets')->insert(['id_user' => 1, 'estado' => 'ENTREGADO']);

        $this->servicio = app(CarnetService::class);
    }

    public function test_una_sola_solicitud_pendiente_por_persona(): void
    {
        $this->assertFalse($this->servicio->solicitar(1, 'PERDIDA', 'Se le cayó', 1)['error']);

        $repetida = $this->servicio->solicitar(1, 'DANO', null, 1);
        $this->assertTrue($repetida['error']);
        $this->assertSame(1, DB::table('carnet_solicitudes')->count());
    }

    public function test_no_se_pide_carnet_para_quien_no_lo_lleva(): void
    {
        $this->assertTrue($this->servicio->solicitar(2, 'PERDIDA', null, 2)['error']);
    }

    public function test_sin_primer_carnet_impreso_no_se_solicita(): void
    {
        $r = $this->servicio->solicitar(3, 'PERDIDA', null, 3);

        $this->assertTrue($r['error']);
        $this->assertStringContainsString('primer carnet', $r['message']);
        $this->assertSame(0, DB::table('carnet_solicitudes')->count());
    }

    public function test_responder_cierra_la_pendiente_una_sola_vez_y_queda_en_el_seguimiento(): void
    {
        $id = $this->servicio->solicitar(1, 'CAMBIO_DATOS', null, 1)['data']['id'];

        $this->assertFalse($this->servicio->responderSolicitud($id, 'RECHAZADA', 'Datos iguales', 9)['error']);
        $this->assertTrue($this->servicio->responderSolicitud($id, 'ATENDIDA', null, 9)['error']);

        // Ya sin pendiente, se puede volver a pedir; el seguimiento trae las dos, la nueva primero.
        $nueva = $this->servicio->solicitar(1, 'PERDIDA', null, 1)['data']['id'];
        $seguimiento = collect($this->servicio->seguimiento([1])['data']);

        $this->assertSame([$nueva, $id], $seguimiento->pluck('id')->map(fn ($v) => (int) $v)->all());
        $this->assertSame(['PENDIENTE', 'RECHAZADA'], $seguimiento->pluck('estado')->all());
        $this->assertSame('Datos iguales', $seguimiento->last()->respuesta);
    }
}
