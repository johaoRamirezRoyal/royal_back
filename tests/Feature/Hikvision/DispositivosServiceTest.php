<?php

namespace Tests\Feature\Hikvision;

use App\Models\Hikvision\HikvisionDispositivo;
use App\Services\Hikvisionattendance\DispositivosService;
use App\Services\Hikvisionattendance\hikvisionattendanceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Terminales por institución. Las dos instituciones (`mysql` = Royal, `sami_hebreo` = Hebreo)
 * se apuntan a bases sqlite en memoria separadas, cada una con su tabla hikvision_dispositivos
 * (base + la migración real de las columnas de conexión): nunca se toca una base real.
 */
class DispositivosServiceTest extends TestCase
{
    private DispositivosService $service;

    private const DATOS = [
        'nombre' => 'Garita',
        'protocolo' => 'http',
        'host' => '10.0.0.5',
        'puerto' => 80,
        'usuario' => 'admin',
        'password' => 'secreta',
        'principal' => true,
        'activo' => true,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', DB::getDriverName(), 'Corre los tests con `composer run test` (limpia la caché de config).');

        config(['services.hikvision.legado' => []]); // sin importar terminales del .env
        foreach (['mysql', 'sami_hebreo'] as $institucion) {
            config(["database.connections.{$institucion}" => config('database.connections.sqlite')]);
            DB::purge($institucion);
            $this->prepararInstitucion($institucion);
        }
        config(['database.default' => 'mysql']); // la institución del usuario en sesión

        $this->service = app(DispositivosService::class);
    }

    /** La migración original declara la PK dos veces (MySQL lo acepta, sqlite no): la tabla base se crea aquí. */
    private function prepararInstitucion(string $institucion): void
    {
        config(['database.default' => $institucion]);
        Schema::create('hikvision_dispositivos', function (Blueprint $table) {
            $table->increments('id');
            $table->string('device_id', 100)->unique();
            $table->string('nombre', 100);
            $table->timestamp('fechareg')->useCurrent();
            $table->timestamp('fecha_updated')->nullable();
        });
        (require database_path('migrations/2026_10_26_100000_add_conexion_to_hikvision_dispositivos_table.php'))->up();
    }

    private function enHebreo(array $datos): void
    {
        config(['database.default' => 'sami_hebreo']);
        $this->assertFalse($this->service->guardar($datos)['error']);
        config(['database.default' => 'mysql']);
    }

    public function test_crear_cifra_la_contrasena_y_calcula_el_device_id(): void
    {
        $r = $this->service->guardar(self::DATOS);

        $this->assertFalse($r['error'], $r['message'] ?? '');
        $d = HikvisionDispositivo::first();
        $this->assertSame('10.0.0.5:80', $d->device_id);
        $this->assertSame('secreta', $d->password);
        $this->assertNotSame('secreta', DB::table('hikvision_dispositivos')->value('password'));
        $this->assertArrayNotHasKey('password', $d->toArray());
        $this->assertTrue($d->toArray()['tiene_password']);
    }

    public function test_crear_sin_contrasena_y_direccion_repetida_se_rechazan(): void
    {
        $this->assertTrue($this->service->guardar([...self::DATOS, 'password' => ''])['error']);

        $this->service->guardar(self::DATOS);
        $r = $this->service->guardar([...self::DATOS, 'nombre' => 'Otra']);
        $this->assertTrue($r['error']);
        $this->assertSame(422, $r['status']);
    }

    public function test_editar_sin_contrasena_conserva_la_guardada_y_cambia_el_device_id(): void
    {
        $id = $this->service->guardar(self::DATOS)['data']->id;

        $this->service->guardar([...self::DATOS, 'puerto' => 8001, 'password' => ''], $id);

        $d = HikvisionDispositivo::find($id);
        $this->assertSame('10.0.0.5:8001', $d->device_id);
        $this->assertSame('secreta', $d->password);
    }

    public function test_solo_una_principal(): void
    {
        $a = $this->service->guardar(self::DATOS)['data']->id;
        $b = $this->service->guardar([...self::DATOS, 'host' => '10.0.0.6'])['data']->id;

        $this->assertFalse(HikvisionDispositivo::find($a)->principal);
        $this->assertTrue(HikvisionDispositivo::find($b)->principal);
    }

    public function test_eliminar(): void
    {
        $id = $this->service->guardar(self::DATOS)['data']->id;

        $this->assertFalse($this->service->eliminar($id)['error']);
        $this->assertSame(404, $this->service->eliminar($id)['status']);
    }

    public function test_cada_institucion_tiene_sus_terminales(): void
    {
        $this->service->guardar(self::DATOS);
        $this->enHebreo([...self::DATOS, 'host' => '10.0.0.7', 'nombre' => 'Hebreo']);

        $this->assertSame(['10.0.0.5:80'], HikvisionDispositivo::pluck('device_id')->all());
        $this->assertSame(['10.0.0.7:80'], HikvisionDispositivo::on('sami_hebreo')->pluck('device_id')->all());
        // Marcar la principal en una institución no toca la de la otra.
        $this->assertTrue(HikvisionDispositivo::first()->principal);
    }

    public function test_una_terminal_no_puede_estar_en_dos_instituciones(): void
    {
        $this->enHebreo(self::DATOS);

        $r = $this->service->guardar(self::DATOS);

        $this->assertTrue($r['error']);
        $this->assertSame(422, $r['status']);
        $this->assertStringContainsString('otra institución', $r['message']);
    }

    public function test_el_webhook_no_adivina_si_la_ip_es_de_dos_instituciones(): void
    {
        $this->service->guardar([...self::DATOS, 'host' => '190.0.0.1', 'puerto' => 8001]);
        $this->assertSame('mysql', hikvisionattendanceService::resolverTenantPorIp('190.0.0.1'));

        $this->enHebreo([...self::DATOS, 'host' => '190.0.0.1', 'puerto' => 8002]);
        $this->assertNull(hikvisionattendanceService::resolverTenantPorIp('190.0.0.1'));
        $this->assertNull(hikvisionattendanceService::resolverTenantPorIp('1.2.3.4'));
    }
}
