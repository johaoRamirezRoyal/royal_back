<?php

namespace Tests\Feature\CursosAltura;

use App\Models\CursosAltura\CursoAltura;
use App\Models\Usuarios\Usuario;
use App\Services\JwtService;
use App\Services\PermisosLicencias\PermisosLicenciasServices;
use App\Support\Permiso;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Contra la base real (ver InventarioFechaReporteTest). Todo corre dentro de una transacción
 * que se revierte al final: cursos_altura, reportes y usuarios son InnoDB.
 */
class CursosAlturaTest extends TestCase
{
    private ?Usuario $operativo;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $env = file_get_contents(base_path('.env'));
        preg_match('/^DB_DATABASE=(.*)$/m', $env, $match);
        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql.database', trim($match[1] ?? 'laravel', " \t\n\r\0\x0B\"'"));
        DB::purge('mysql');
        DB::beginTransaction();

        $this->operativo = Usuario::where('estado', 'activo')
            ->whereIn('perfil', PermisosLicenciasServices::PERFILES_ASIGNABLES)
            ->whereNotIn('id_user', DB::table('cursos_altura')->select('id_user'))
            ->first();
        $admin = Usuario::where('estado', 'activo')->where('perfil', Permiso::PERFIL_SUPER_ADMIN)->first();

        if (! $this->operativo || ! $admin) {
            $this->markTestSkipped('Faltan datos reales (operativo sin curso o super admin).');
        }

        $this->token = app(JwtService::class)->generateToken($admin);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_solo_cuentan_los_cursos_vigentes_de_operativos(): void
    {
        $id = $this->operativo->id_user;
        $this->assertFalse(CursoAltura::operativoVigente($id));

        $this->curso($id, '2020-01-01', '2021-01-01');
        $this->assertFalse(CursoAltura::operativoVigente($id));

        $this->curso($id, now()->subYear()->toDateString(), null);
        $this->assertTrue(CursoAltura::operativoVigente($id));

        $vigentes = $this->getJson('/api/cursos-altura/operativos-vigentes', $this->auth())->assertOk()->json('data');
        $this->assertContains($id, array_column($vigentes, 'id_user'));

        // Un no operativo con curso vigente no cuenta.
        $noOperativo = Usuario::where('estado', 'activo')->whereNotIn('perfil', PermisosLicenciasServices::PERFILES_ASIGNABLES)->value('id_user');
        $this->curso($noOperativo, now()->subYear()->toDateString(), null);
        $this->assertFalse(CursoAltura::operativoVigente($noOperativo));
    }

    public function test_reportar_con_operativo_sin_curso_vigente_da_422(): void
    {
        $item = DB::table('inventario')->whereNotIn('estado', [2, 5])->value('id');

        $this->postJson('/api/inventario/reportar', [
            'ids' => [$item],
            'id_log' => $this->operativo->id_user,
            'descripcion' => 'Prueba automática',
            'id_anio' => DB::table('anio_escolar')->value('id'),
            'id_periodo' => DB::table('periodos')->value('id'),
            'id_operativo' => $this->operativo->id_user,
        ], $this->auth())->assertUnprocessable()->assertJsonValidationErrors('id_operativo');
    }

    public function test_programar_mantenimiento_con_operativo_sin_curso_vigente_da_422(): void
    {
        $this->postJson('/api/inventario/mantenimiento', [
            'ids' => [DB::table('inventario')->whereNotIn('estado', [2, 5, 6])->value('id')],
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->addMonth()->toDateString(),
            'descripcion' => 'Prueba automática',
            'id_anio' => DB::table('anio_escolar')->value('id'),
            'periodo' => DB::table('periodos')->value('id'),
            'id_log' => $this->operativo->id_user,
            'con_solucion' => false,
            'id_operativo' => $this->operativo->id_user,
        ], $this->auth())->assertUnprocessable()->assertJsonPath('message', 'El operativo seleccionado no tiene un curso de altura vigente.');
    }

    public function test_permiso_pdf_de_reporte_sin_operativo_da_404(): void
    {
        $reporte = DB::table('reportes')->whereNull('id_operativo')->whereNull('id_reporte')->value('id');

        $this->get("/api/cursos-altura/permiso/{$reporte}/pdf", $this->auth())->assertNotFound();
    }

    public function test_permiso_pdf_de_reporte_con_operativo(): void
    {
        $this->curso($this->operativo->id_user, now()->subYear()->toDateString(), null);
        $reporte = DB::table('reportes as rp')->join('inventario as iv', 'iv.id', '=', 'rp.id_inventario')
            ->whereNull('rp.id_reporte')->value('rp.id');
        DB::table('reportes')->where('id', $reporte)->update(['id_operativo' => $this->operativo->id_user]);

        $this->get("/api/cursos-altura/permiso/{$reporte}/pdf", $this->auth())
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_crear_guarda_el_certificado_en_el_disco_de_uploads(): void
    {
        Config::set('filesystems.uploads_disk', 'public');
        Storage::fake('public');

        $ruta = $this->post('/api/cursos-altura', [
            'id_user' => $this->operativo->id_user,
            'fecha_expedicion' => now()->subMonth()->toDateString(),
            'certificado' => UploadedFile::fake()->create('curso.pdf', 100, 'application/pdf'),
        ], $this->auth())->assertOk()->json('data.ruta_certificado');

        Storage::disk('public')->assertExists($ruta);
    }

    private function curso(int $idUser, string $expedicion, ?string $fin): void
    {
        DB::table('cursos_altura')->insert([
            'id_user' => $idUser,
            'ruta_certificado' => 'prueba.pdf',
            'fecha_expedicion' => $expedicion,
            'fecha_finalizacion' => $fin,
        ]);
    }

    /** @return array<string, string> */
    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}", 'Accept' => 'application/json'];
    }
}
