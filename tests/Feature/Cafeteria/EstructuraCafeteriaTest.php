<?php

namespace Tests\Feature\Cafeteria;

use App\Enums\Cafeteria\EstadoCafeteria;
use App\Models\Cafeteria\Almacen;
use App\Models\Cafeteria\Cafeteria;
use App\Models\Cafeteria\PuntoVenta;
use App\Models\Usuarios\Usuario;
use App\Services\Cafeteria\EstructuraCafeteriaService;
use App\Support\Permiso;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** Reglas de la estructura de la cafetería (EstructuraCafeteriaService) y su permiso (176). */
class EstructuraCafeteriaTest extends CafeteriaTestCase
{
    private EstructuraCafeteriaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EstructuraCafeteriaService::class);
    }

    public function test_nace_en_borrador_y_solo_se_activa_con_un_punto_de_venta_activo(): void
    {
        $cafeteria = $this->cafeteria('PRUEBA-1');
        $this->assertSame(EstadoCafeteria::BORRADOR, $cafeteria->estado);

        $sinPunto = $this->service->cambiarEstado($cafeteria->id, EstadoCafeteria::ACTIVA, 1);
        $this->assertTrue($sinPunto['error']);
        $this->assertSame(422, $sinPunto['status']);

        $this->punto($cafeteria, 'CAJA-1');
        $this->assertFalse($this->service->cambiarEstado($cafeteria->id, EstadoCafeteria::ACTIVA, 1)['error']);
        $this->assertSame(EstadoCafeteria::ACTIVA, $cafeteria->fresh()->estado);
    }

    public function test_solo_permite_transiciones_validas_y_una_cerrada_no_se_modifica(): void
    {
        $cafeteria = $this->cafeteria('PRUEBA-2');

        $this->assertTrue($this->service->cambiarEstado($cafeteria->id, EstadoCafeteria::SUSPENDIDA, 1)['error']);
        $this->assertFalse($this->service->cambiarEstado($cafeteria->id, EstadoCafeteria::CERRADA, 1)['error']);
        $this->assertTrue($this->service->cambiarEstado($cafeteria->id, EstadoCafeteria::ACTIVA, 1)['error']);

        $this->assertTrue($this->service->actualizarCafeteria($cafeteria->id, ['nombre' => 'Otro'], 1)['error']);
        $this->assertTrue($this->service->crearPuntoVenta($cafeteria->id, ['nombre' => 'X', 'tipo' => 'MOSTRADOR'])['error']);
    }

    public function test_almacenes_del_punto_exigen_un_predeterminado_y_que_sean_centrales_o_propios(): void
    {
        $cafeteria = $this->cafeteria('PRUEBA-3');
        $otra = $this->cafeteria('PRUEBA-4');
        $punto = $this->punto($cafeteria, 'CAJA-1');
        $propio = $this->almacen('ALM-PROPIO', $cafeteria->id);
        $central = $this->almacen('ALM-CENTRAL', null);
        $ajeno = $this->almacen('ALM-AJENO', $otra->id);

        $ninguno = $this->service->guardarAlmacenesPunto($punto->id, [
            ['id_almacen' => $propio->id, 'es_predeterminado' => false],
        ]);
        $this->assertTrue($ninguno['error']);

        $conAjeno = $this->service->guardarAlmacenesPunto($punto->id, [
            ['id_almacen' => $propio->id, 'es_predeterminado' => true],
            ['id_almacen' => $ajeno->id, 'es_predeterminado' => false],
        ]);
        $this->assertTrue($conAjeno['error']);

        $bien = $this->service->guardarAlmacenesPunto($punto->id, [
            ['id_almacen' => $propio->id, 'es_predeterminado' => false],
            ['id_almacen' => $central->id, 'es_predeterminado' => true],
        ]);
        $this->assertFalse($bien['error']);
        $this->assertSame($central->id, $punto->almacenes()->wherePivot('es_predeterminado', true)->value('caf_almacenes.id'));

        // El UNIQUE de la base también lo impide, aunque alguien escriba sin pasar por el service.
        $this->expectException(QueryException::class);
        DB::table('caf_puntos_venta_almacenes')->where('id_punto_venta', $punto->id)->update(['es_predeterminado' => true]);
    }

    public function test_un_almacen_no_cambia_de_cafeteria_si_abastece_puntos_de_otra(): void
    {
        $cafeteria = $this->cafeteria('PRUEBA-5');
        $otra = $this->cafeteria('PRUEBA-6');
        $punto = $this->punto($cafeteria, 'CAJA-1');
        $central = $this->almacen('ALM-CENTRAL-2', null);
        $this->service->guardarAlmacenesPunto($punto->id, [['id_almacen' => $central->id, 'es_predeterminado' => true]]);

        $this->assertTrue($this->service->actualizarAlmacen($central->id, ['id_cafeteria' => $otra->id])['error']);
        $this->assertFalse($this->service->actualizarAlmacen($central->id, ['id_cafeteria' => $cafeteria->id])['error']);
    }

    public function test_no_se_desactiva_el_almacen_predeterminado_de_un_punto_activo(): void
    {
        $cafeteria = $this->cafeteria('PRUEBA-8');
        $punto = $this->punto($cafeteria, 'CAJA-1');
        $principal = $this->almacen('ALM-PRINCIPAL', $cafeteria->id);
        $apoyo = $this->almacen('ALM-APOYO', $cafeteria->id);
        $this->service->guardarAlmacenesPunto($punto->id, [
            ['id_almacen' => $principal->id, 'es_predeterminado' => true],
            ['id_almacen' => $apoyo->id, 'es_predeterminado' => false],
        ]);

        $this->assertTrue($this->service->actualizarAlmacen($principal->id, ['estado' => 'INACTIVO'])['error']);
        $this->assertTrue($this->service->actualizarAlmacen($principal->id, ['estado' => 'BLOQUEADO'])['error']);
        $this->assertFalse($this->service->actualizarAlmacen($apoyo->id, ['estado' => 'INACTIVO'])['error'], 'El que no es predeterminado sí');

        // Con el punto inactivo ya no lo abastece: se puede.
        $this->service->actualizarPuntoVenta($punto->id, ['estado' => 'INACTIVO']);
        $this->assertFalse($this->service->actualizarAlmacen($principal->id, ['estado' => 'INACTIVO'])['error']);
    }

    public function test_un_almacen_de_una_cafeteria_cerrada_no_se_modifica_ni_se_saca(): void
    {
        $cerrada = $this->cafeteria('PRUEBA-9');
        $abierta = $this->cafeteria('PRUEBA-10');
        $almacen = $this->almacen('ALM-CERRADA', $cerrada->id);
        $this->service->cambiarEstado($cerrada->id, EstadoCafeteria::CERRADA, 1);

        $this->assertTrue($this->service->actualizarAlmacen($almacen->id, ['id_cafeteria' => $abierta->id])['error']);
        $this->assertTrue($this->service->actualizarAlmacen($almacen->id, ['id_cafeteria' => null])['error']);
        $this->assertTrue($this->service->actualizarAlmacen($almacen->id, ['nombre' => 'Otro'])['error']);
        $this->assertSame($cerrada->id, $almacen->fresh()->id_cafeteria);
    }

    public function test_lo_que_no_existe_responde_404(): void
    {
        foreach ([
            $this->service->actualizarPuntoVenta(999999999, ['nombre' => 'X']),
            $this->service->guardarAlmacenesPunto(999999999, []),
            $this->service->actualizarTerminal(999999999, ['nombre' => 'X']),
            $this->service->actualizarAlmacen(999999999, ['nombre' => 'X']),
            $this->service->cambiarEstado(999999999, EstadoCafeteria::ACTIVA, 1),
        ] as $respuesta) {
            $this->assertTrue($respuesta['error']);
            $this->assertSame(404, $respuesta['status']);
        }
    }

    public function test_las_franjas_del_mismo_dia_no_se_cruzan(): void
    {
        $cafeteria = $this->cafeteria('PRUEBA-7');

        $cruzadas = $this->service->guardarHorariosCafeteria($cafeteria->id, [
            ['id_dia_semana' => 1, 'hora_apertura' => '07:00', 'hora_cierre' => '10:00'],
            ['id_dia_semana' => 1, 'hora_apertura' => '09:30', 'hora_cierre' => '12:00'],
        ]);
        $this->assertTrue($cruzadas['error']);

        $bien = $this->service->guardarHorariosCafeteria($cafeteria->id, [
            ['id_dia_semana' => 1, 'hora_apertura' => '07:00', 'hora_cierre' => '10:00'],
            ['id_dia_semana' => 1, 'hora_apertura' => '12:00', 'hora_cierre' => '14:00'],
            ['id_dia_semana' => 2, 'hora_apertura' => '07:00', 'hora_cierre' => '14:00'],
        ]);
        $this->assertFalse($bien['error']);
        $this->assertCount(3, $bien['data']);
    }

    public function test_la_api_rechaza_a_quien_no_tiene_la_opcion_176(): void
    {
        $sinOpcion = Usuario::where('estado', 'activo')->where('perfil', '<>', Permiso::PERFIL_SUPER_ADMIN)->first()
            ?? $this->markTestSkipped('Falta un usuario activo que no sea Super Admin.');
        DB::table('cron_permisos')->where('id_opcion', Permiso::CAFETERIA_ESTRUCTURA)->delete();

        $this->getJson('/api/cafeteria/cafeterias', $this->auth($sinOpcion))->assertForbidden();
        $this->getJson('/api/cafeteria/catalogos', $this->auth($sinOpcion))->assertForbidden();
    }

    /** Un usuario por prueba: el guard JWT conserva al primero que se autentica en la prueba. */
    public function test_la_api_crea_en_borrador_con_codigo_asignado_e_inmutable(): void
    {
        $superAdmin = Usuario::where('estado', 'activo')->where('perfil', Permiso::PERFIL_SUPER_ADMIN)->first()
            ?? $this->markTestSkipped('Falta un Super Admin activo.');

        // Un código que llegue del cliente se ignora: lo asigna el sistema.
        $creada = $this->postJson('/api/cafeteria/cafeterias', ['codigo' => 'MIO', 'nombre' => 'Cafetería'], $this->auth($superAdmin))
            ->assertOk()
            ->assertJsonPath('data.estado', 'BORRADOR')
            ->json('data');
        $this->assertMatchesRegularExpression('/^CAF-\d{3,}$/', $creada['codigo']);

        $this->putJson("/api/cafeteria/cafeterias/{$creada['id']}", ['codigo' => 'OTRO', 'nombre' => 'Renombrada'], $this->auth($superAdmin))
            ->assertOk()
            ->assertJsonPath('data.codigo', $creada['codigo'])
            ->assertJsonPath('data.nombre', 'Renombrada');
    }

    public function test_los_codigos_son_consecutivos_y_por_padre_donde_corresponde(): void
    {
        $base = (int) (Cafeteria::where('codigo', 'like', 'CAF-%')->pluck('codigo')
            ->map(fn ($c) => (int) substr($c, 4))->max() ?? 0);
        $a = $this->cafeteria('A');
        $b = $this->cafeteria('B');
        $this->assertSame(sprintf('CAF-%03d', $base + 1), $a->codigo);
        $this->assertSame(sprintf('CAF-%03d', $base + 2), $b->codigo);

        // Puntos y terminales cuentan dentro de su padre: cada cafetería empieza en PV-001.
        $this->assertSame('PV-001', $this->punto($a, 'Caja')->codigo);
        $this->assertSame('PV-002', $this->punto($a, 'Caja 2')->codigo);
        $puntoB = $this->punto($b, 'Caja');
        $this->assertSame('PV-001', $puntoB->codigo);
        $this->assertSame('TER-001', $this->service->crearTerminal($puntoB->id, ['nombre' => 'T'])['data']->codigo);

        $this->assertMatchesRegularExpression('/^ALM-\d{3,}$/', $this->almacen('Bodega', null)->codigo);
    }

    public function test_el_codigo_de_una_funcion_sale_del_nombre_y_no_se_repite(): void
    {
        $primera = $this->service->crearRol(['nombre' => 'Auxiliar de caja (tarde)'])['data'];
        $segunda = $this->service->crearRol(['nombre' => 'Auxiliar de caja – tarde'])['data'];

        $this->assertSame('AUXILIAR_DE_CAJA_TARDE', $primera->codigo);
        $this->assertSame('AUXILIAR_DE_CAJA_TARDE_2', $segunda->codigo);
        $this->assertSame('CAJERO_2', $this->service->crearRol(['nombre' => 'Cajero'])['data']->codigo, 'Las sembradas también cuentan');

        // Un nombre largo se recorta (deja sitio al sufijo _NN dentro de los 30) y sin guion bajo colgando.
        $larga = $this->service->crearRol(['nombre' => 'Encargada de la cocina de producción y empaques'])['data'];
        $this->assertLessThanOrEqual(27, strlen($larga->codigo));
        $this->assertStringEndsNotWith('_', $larga->codigo);
    }

    /** Editar un almacén sin mandar `id_cafeteria` lo deja en su cafetería (antes lo volvía central). */
    public function test_editar_un_almacen_sin_id_cafeteria_conserva_su_cafeteria(): void
    {
        $superAdmin = Usuario::where('estado', 'activo')->where('perfil', Permiso::PERFIL_SUPER_ADMIN)->first()
            ?? $this->markTestSkipped('Falta un Super Admin activo.');
        $cafeteria = $this->cafeteria('PRUEBA-11');
        $almacen = $this->almacen('ALM-PROPIO-2', $cafeteria->id);

        $this->putJson("/api/cafeteria/almacenes/{$almacen->id}", ['nombre' => 'Renombrado', 'tipo' => 'GENERAL'], $this->auth($superAdmin))
            ->assertOk()
            ->assertJsonPath('data.id_cafeteria', $cafeteria->id);

        $this->assertSame('Renombrado', $almacen->fresh()->nombre);
        $this->assertSame($cafeteria->id, $almacen->fresh()->id_cafeteria);
    }

    private function cafeteria(string $nombre): Cafeteria
    {
        return $this->service->crearCafeteria(['nombre' => "Cafetería {$nombre}"], 1)['data'];
    }

    private function punto(Cafeteria $cafeteria, string $nombre): PuntoVenta
    {
        return $this->service->crearPuntoVenta($cafeteria->id, ['nombre' => $nombre, 'tipo' => 'MOSTRADOR'])['data'];
    }

    private function almacen(string $nombre, ?int $idCafeteria): Almacen
    {
        return $this->service->crearAlmacen(['nombre' => $nombre, 'tipo' => 'GENERAL', 'id_cafeteria' => $idCafeteria], 1)['data'];
    }
}
