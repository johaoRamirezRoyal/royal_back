<?php

namespace Tests\Feature\Hikvision;

use App\Models\AsistenciaGestion\AsistenciaGestion;
use App\Services\AsistenciaTrabajadores\AsistenciaGestionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Terminal sin red que envía sus eventos horas después (2026-11-09): la entrada es la
 * marcación más temprana del día y la salida la más tardía, lleguen en el orden que lleguen.
 * Tablas mínimas en sqlite en memoria; nunca toca una base real.
 */
class MarcacionAtrasadaTest extends TestCase
{
    private AsistenciaGestionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', DB::getDriverName(), 'Corre los tests con `composer run test` (limpia la caché de config).');

        Schema::create('usuarios', function (Blueprint $t) {
            $t->increments('id_user');
            $t->integer('perfil')->nullable();
        });
        Schema::create('asistencia_horarios_estandar', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('grupo_id')->nullable();
            $t->boolean('activo')->default(true);
            $t->text('dias_habiles')->nullable();
            $t->time('hora_minima_salida')->nullable();
        });
        Schema::create('asistencia_puntualidad_bandas', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('id_horario');
            $t->integer('orden')->default(0);
        });
        Schema::create('configuracion_asistencia', function (Blueprint $t) {
            $t->increments('id');
            $t->boolean('notificar_llegada_tarde')->default(false);
            $t->time('hora_minima_salida_defecto')->nullable();
        });
        Schema::create('asistencia_gestion', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('id_user');
            $t->date('fecha_asistencia');
            $t->time('hora_asistencia')->nullable();
            $t->time('hora_salida')->nullable();
            $t->text('observacion')->nullable();
            $t->boolean('revocado')->default(false);
            $t->integer('QR')->default(0);
            $t->dateTime('fechareg')->nullable();
            $t->unique(['id_user', 'fecha_asistencia']);
        });
        // El cast 'date' guarda "Y-m-d H:i:s"; MySQL lo trunca a la fecha en una columna DATE,
        // sqlite no. Sin esto, buscar por fecha no encuentra la fila.
        DB::statement("CREATE TRIGGER fecha_solo_dia AFTER INSERT ON asistencia_gestion BEGIN
            UPDATE asistencia_gestion SET fecha_asistencia = date(NEW.fecha_asistencia) WHERE id = NEW.id; END");
        Schema::create('asistencia_gestion_marcaciones', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('id_asistencia');
            $t->time('hora');
        });

        // El grupo de Hikvision (y con él el horario) sale del tipo de perfil y de la opción 171.
        Schema::create('perfiles', function (Blueprint $t) {
            $t->increments('id_perfil');
            $t->string('tipo')->nullable();
        });
        Schema::create('cron_modulos', fn (Blueprint $t) => $t->increments('id'));
        Schema::create('cron_opciones', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('id_modulo');
        });
        Schema::create('cron_permisos', function (Blueprint $t) {
            $t->integer('id_opcion');
            $t->integer('id_perfil');
            $t->boolean('activo')->default(true);
        });
        DB::table('perfiles')->insert(['id_perfil' => 3, 'tipo' => 'DOCENTE']);
        (fn () => self::$gruposPorPerfil = [])->call(new \App\Services\Hikvisionattendance\hikvisionattendanceService());

        DB::table('usuarios')->insert(['id_user' => 2943, 'perfil' => 3]);
        DB::table('asistencia_horarios_estandar')->insert([
            'grupo_id' => null,
            'activo' => true,
            'dias_habiles' => json_encode([1, 2, 3, 4, 5, 6, 7]),
            'hora_minima_salida' => '12:00:00',
        ]);

        // Caché estática de horarios del modelo: otra prueba pudo haberla llenado.
        (fn () => self::$horariosCache = null)->call(new AsistenciaGestion());

        $this->service = app(AsistenciaGestionService::class);
    }

    private function fila(): AsistenciaGestion
    {
        return AsistenciaGestion::where('id_user', 2943)->where('fecha_asistencia', '2026-11-09')->firstOrFail();
    }

    public function test_una_marcacion_anterior_que_llega_despues_pasa_a_ser_la_entrada(): void
    {
        // Llega primero la de la tarde, luego la de la mañana (orden invertido).
        $this->service->registrarMarcacion(2943, '2026-11-09', '13:40:46');
        $this->service->registrarMarcacion(2943, '2026-11-09', '06:22:49');

        $fila = $this->fila();
        $this->assertSame('06:22:49', $fila->hora_asistencia->format('H:i:s'));
        // La entrada que se reemplazó sigue contando: como es después de la hora mínima, es la salida.
        $this->assertSame('13:40:46', $fila->hora_salida?->format('H:i:s'));
    }

    public function test_en_orden_cronologico_la_entrada_y_la_salida_no_cambian(): void
    {
        $this->service->registrarMarcacion(2943, '2026-11-09', '06:22:49');
        $this->service->registrarMarcacion(2943, '2026-11-09', '13:40:46');
        $this->service->registrarMarcacion(2943, '2026-11-09', '10:00:00'); // antes de la hora mínima: solo marcación

        $fila = $this->fila();
        $this->assertSame('06:22:49', $fila->hora_asistencia->format('H:i:s'));
        $this->assertSame('13:40:46', $fila->hora_salida?->format('H:i:s'));
        $this->assertSame(2, DB::table('asistencia_gestion_marcaciones')->count());
    }
}
