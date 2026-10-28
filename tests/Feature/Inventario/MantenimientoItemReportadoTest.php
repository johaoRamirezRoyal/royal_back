<?php

namespace Tests\Feature\Inventario;

use App\Models\Usuarios\Usuario;
use App\Services\JwtService;
use App\Support\Permiso;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Contra la base real (ver InventarioFechaReporteTest), dentro de una transacción que se
 * revierte: un ítem reportado admite un preventivo y conserva su estado; uno con un
 * preventivo abierto no admite otro.
 */
class MantenimientoItemReportadoTest extends TestCase
{
    private int $item;

    private string $token;

    private int $idLog;

    protected function setUp(): void
    {
        parent::setUp();

        $env = file_get_contents(base_path('.env'));
        preg_match('/^DB_DATABASE=(.*)$/m', $env, $match);
        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql.database', trim($match[1] ?? 'laravel', " \t\n\r\0\x0B\"'"));
        DB::purge('mysql');
        DB::beginTransaction();
        Mail::fake();

        $admin = Usuario::where('estado', 'activo')->where('perfil', Permiso::PERFIL_SUPER_ADMIN)->first();
        $item = DB::table('inventario as iv')
            ->where('iv.activo', 1)
            ->where('iv.estado', '!=', 5)
            ->whereNotExists(fn ($q) => $q->from('reportes as r')->whereColumn('r.id_inventario', 'iv.id')
                ->where('r.tipo_reporte', 2)->where('r.estado', 6)->whereNull('r.id_reporte'))
            ->value('iv.id');

        if (! $admin || ! $item) {
            $this->markTestSkipped('Faltan datos reales (super admin o ítem sin preventivo).');
        }

        $this->item = $item;
        $this->idLog = $admin->id_user;
        $this->token = app(JwtService::class)->generateToken($admin);

        // Se deja reportado (estado 2), como queda tras POST /inventario/reportar.
        DB::table('inventario')->where('id', $item)->update(['estado' => 2]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_un_item_reportado_admite_preventivo_y_conserva_su_estado(): void
    {
        $this->programar()->assertOk()->assertJsonPath('error', false);

        $this->assertSame(2, (int) DB::table('inventario')->where('id', $this->item)->value('estado'));
        $this->assertTrue(DB::table('reportes')->where('id_inventario', $this->item)
            ->where('tipo_reporte', 2)->where('estado', 6)->whereNull('id_reporte')->exists());
    }

    public function test_no_admite_otro_preventivo_si_ya_tiene_uno_abierto(): void
    {
        $this->programar()->assertOk();

        $this->programar()->assertStatus(400)->assertJsonPath('error', true);
        $this->assertSame(1, DB::table('reportes')->where('id_inventario', $this->item)
            ->where('tipo_reporte', 2)->where('estado', 6)->whereNull('id_reporte')->count());
    }

    private function programar(): TestResponse
    {
        return $this->postJson('/api/inventario/mantenimiento', [
            'ids' => [$this->item],
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->addMonth()->toDateString(),
            'descripcion' => 'Prueba automática',
            'id_anio' => DB::table('anio_escolar')->value('id'),
            'periodo' => DB::table('periodos')->value('id'),
            'id_log' => $this->idLog,
            'con_solucion' => false,
        ], ['Authorization' => "Bearer {$this->token}"]);
    }
}
