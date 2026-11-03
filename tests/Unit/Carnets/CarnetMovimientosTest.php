<?php

namespace Tests\Unit\Carnets;

use App\Services\Carnets\CarnetService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * `carnet_movimientos` en sqlite en memoria: `carnets` y `usuarios` se crean a mano (la de
 * usuarios es legacy y la migración de REIMPRESO es SQL de MySQL).
 */
class CarnetMovimientosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // El servicio arma los nombres con CONCAT de MySQL; este sqlite no lo trae.
        DB::connection()->getPdo()->sqliteCreateFunction('CONCAT', fn (...$partes) => implode('', $partes), -1);

        Schema::create('usuarios', function (Blueprint $t) {
            $t->increments('id_user');
            $t->string('nombre');
            $t->string('apellido')->nullable();
        });
        Schema::create('carnets', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('id_user');
            $t->unsignedSmallInteger('version');
            $t->string('estado');
            $t->dateTime('fecha_emision');
            $t->dateTime('fecha_entrega')->nullable();
            $t->dateTime('fecha_estado')->nullable();
            $t->string('observacion')->nullable();
            $t->unsignedInteger('id_usuario_emite');
            $t->unsignedInteger('id_usuario_estado')->nullable();
        });
        DB::table('usuarios')->insert([['nombre' => 'Ana', 'apellido' => 'Ruiz'], ['nombre' => 'Luis', 'apellido' => 'Paz']]);
    }

    private function migrar(): void
    {
        (require database_path('migrations/2026_11_03_120000_create_carnet_movimientos_table.php'))->up();
    }

    private function movimientos(int $idCarnet): array
    {
        return DB::table('carnet_movimientos')->where('id_carnet', $idCarnet)->orderBy('id')
            ->get(['estado', 'id_usuario', 'observacion'])->map(fn ($m) => (array) $m)->all();
    }

    public function test_la_migracion_llena_los_movimientos_de_los_carnets_existentes(): void
    {
        DB::table('carnets')->insert([
            // Entregado y luego anulado: no se sabe quién entregó.
            ['id_user' => 9, 'version' => 1, 'estado' => 'ANULADO', 'fecha_emision' => '2026-10-29 10:00:00', 'fecha_entrega' => '2026-10-29 11:00:00',
                'fecha_estado' => '2026-10-30 09:00:00', 'observacion' => 'Reemplazado por la versión 2', 'id_usuario_emite' => 1, 'id_usuario_estado' => 2],
            ['id_user' => 9, 'version' => 2, 'estado' => 'ENTREGADO', 'fecha_emision' => '2026-10-30 09:00:00', 'fecha_entrega' => '2026-10-30 12:00:00',
                'fecha_estado' => '2026-10-30 12:00:00', 'observacion' => null, 'id_usuario_emite' => 1, 'id_usuario_estado' => 2],
        ]);

        $this->migrar();

        $this->assertSame([
            ['estado' => 'IMPRESO', 'id_usuario' => 1, 'observacion' => null],
            ['estado' => 'ENTREGADO', 'id_usuario' => null, 'observacion' => null],
            ['estado' => 'ANULADO', 'id_usuario' => 2, 'observacion' => 'Reemplazado por la versión 2'],
        ], $this->movimientos(1));
        $this->assertSame([
            ['estado' => 'REIMPRESO', 'id_usuario' => 1, 'observacion' => null],
            ['estado' => 'ENTREGADO', 'id_usuario' => 2, 'observacion' => null],
        ], $this->movimientos(2));
    }

    public function test_entregar_y_anular_conservan_quien_entrego(): void
    {
        $this->migrar();
        $id = DB::table('carnets')->insertGetId(['id_user' => 9, 'version' => 1, 'estado' => 'IMPRESO', 'fecha_emision' => '2026-11-01 08:00:00', 'id_usuario_emite' => 1]);
        $servicio = app(CarnetService::class);

        $servicio->entregar([9], 1);
        $servicio->cambiarEstado($id, 'ANULADO', 'Dañado', 2);

        $this->assertSame([
            ['estado' => 'ENTREGADO', 'id_usuario' => 1, 'observacion' => null],
            ['estado' => 'ANULADO', 'id_usuario' => 2, 'observacion' => 'Dañado'],
        ], $this->movimientos($id));

        $historial = $servicio->historial(9)['data'][0]->movimientos->all();
        $this->assertSame(['ENTREGADO', 'ANULADO'], array_column($historial, 'estado'));
        $this->assertSame(['Ana Ruiz', 'Luis Paz'], array_column($historial, 'por'));
    }
}
