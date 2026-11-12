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
 * que se revierte al final; cursos_altura puede ser MyISAM, así que sus filas nuevas se borran en tearDown.
 */
class CursosAlturaTest extends TestCase
{
    private ?Usuario $operativo;

    private string $token;

    private int $ultimoCurso = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $env = file_get_contents(base_path('.env'));
        preg_match('/^DB_DATABASE=(.*)$/m', $env, $match);
        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql.database', trim($match[1] ?? 'laravel', " \t\n\r\0\x0B\"'"));
        DB::purge('mysql');
        // cursos_altura puede ser MyISAM (lo es en local): el rollBack no la deshace.
        $this->ultimoCurso = (int) DB::table('cursos_altura')->max('id');
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
        DB::table('cursos_altura')->where('id', '>', $this->ultimoCurso)->delete();
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
            'id_operativos' => [$this->operativo->id_user],
        ], $this->auth())->assertUnprocessable()->assertJsonValidationErrors('id_operativos.0');
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
            'id_operativos' => [$this->operativo->id_user],
        ], $this->auth())->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_ends_with($m, 'no tiene un curso de altura vigente.'));
    }

    public function test_permiso_pdf_de_reporte_sin_trabajadores_da_404(): void
    {
        $reporte = DB::table('reportes')->whereNull('id_reporte')
            ->whereNotIn('id', DB::table('trabajos_altura')->select('id_reporte'))->value('id');

        $this->get("/api/cursos-altura/permiso/{$reporte}/pdf", $this->auth())->assertNotFound();
    }

    public function test_reportar_con_dos_trabajadores_crea_un_trabajo_y_el_pdf_los_lista(): void
    {
        [$a, $b] = $this->dosOperativosVigentes();
        $item = DB::table('inventario')->whereNotIn('estado', [2, 5])->value('id');

        $this->postJson('/api/inventario/reportar', [
            'ids' => [$item],
            'id_log' => $a,
            'descripcion' => 'Prueba automática',
            'id_anio' => DB::table('anio_escolar')->value('id'),
            'id_periodo' => DB::table('periodos')->value('id'),
            'id_operativos' => [$a, $b],
        ], $this->auth())->assertOk();

        $reporte = DB::table('reportes')->where('id_inventario', $item)->whereNull('id_reporte')->max('id');
        $trabajo = DB::table('trabajos_altura')->where('id_reporte', $reporte)->first();
        $this->assertNotNull($trabajo);
        $this->assertEqualsCanonicalizing([$a, $b], DB::table('trabajos_altura_operativos')->where('id_trabajo', $trabajo->id)->pluck('id_operativo')->all());

        $this->get("/api/cursos-altura/permiso/{$reporte}/pdf", $this->auth())
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_editar_trabajo_valida_el_curso_en_la_fecha_del_trabajo_y_sin_trabajadores_lo_borra(): void
    {
        [$a] = $this->dosOperativosVigentes();
        $trabajo = $this->trabajo([$a]);

        // El curso de $a arranca hace un año: no cubre una fecha de hace dos.
        $this->putJson("/api/cursos-altura/trabajos/{$trabajo}", [
            'fecha_trabajo' => now()->subYears(2)->toDateString(),
            'id_operativos' => [$a],
        ], $this->auth())->assertUnprocessable()->assertJsonValidationErrors('id_operativos.0');

        $this->putJson("/api/cursos-altura/trabajos/{$trabajo}", [
            'fecha_trabajo' => now()->subMonth()->toDateString(),
            'id_operativos' => [$a],
        ], $this->auth())->assertOk();
        $this->assertSame(now()->subMonth()->toDateString(), DB::table('trabajos_altura')->where('id', $trabajo)->value('fecha_trabajo'));

        $fila = collect($this->getJson('/api/cursos-altura/trabajos?per-page=500', $this->auth())->assertOk()->json('data.data'))->firstWhere('id', $trabajo);
        $this->assertSame([$a], array_column($fila['operativos'], 'id_user'));

        $this->putJson("/api/cursos-altura/trabajos/{$trabajo}", [
            'fecha_trabajo' => now()->toDateString(),
            'id_operativos' => [],
        ], $this->auth())->assertOk();
        $this->assertFalse(DB::table('trabajos_altura')->where('id', $trabajo)->exists());
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

    public function test_seguridad_social_sin_la_opcion_150_da_403(): void
    {
        if (DB::table('cron_permisos')->where('id_perfil', $this->operativo->perfil)->where('id_opcion', Permiso::SEGURIDAD_SOCIAL)->where('activo', 1)->exists()) {
            $this->markTestSkipped('El perfil operativo ya tiene la opción 150.');
        }

        $auth = ['Authorization' => 'Bearer '.app(JwtService::class)->generateToken($this->operativo), 'Accept' => 'application/json'];

        $this->getJson('/api/cursos-altura/entidades', $auth)->assertForbidden();
        $this->postJson("/api/cursos-altura/seguridad-social/{$this->operativo->id_user}", [], $auth)->assertForbidden();
    }

    public function test_entidad_repetida_en_el_mismo_tipo_da_422(): void
    {
        $this->postJson('/api/cursos-altura/entidades', ['tipo' => 'EPS', 'nombre' => 'EPS Prueba Automática'], $this->auth())->assertOk();
        $this->postJson('/api/cursos-altura/entidades', ['tipo' => 'EPS', 'nombre' => 'EPS Prueba Automática'], $this->auth())
            ->assertUnprocessable()->assertJsonValidationErrors('nombre');
        // Mismo nombre en otro tipo sí se permite.
        $this->postJson('/api/cursos-altura/entidades', ['tipo' => 'ARL', 'nombre' => 'EPS Prueba Automática'], $this->auth())->assertOk();
    }

    public function test_entidad_desactivada_sigue_asignada_y_se_reactiva_al_crearla_de_nuevo(): void
    {
        $id = $this->operativo->id_user;
        $this->curso($id, now()->subYear()->toDateString(), null);
        $afp = $this->postJson('/api/cursos-altura/entidades', ['tipo' => 'AFP', 'nombre' => 'AFP Prueba Automática'], $this->auth())->json('data.id');
        $this->post("/api/cursos-altura/seguridad-social/{$id}", ['id_afp' => $afp], $this->auth())->assertOk();

        $this->putJson("/api/cursos-altura/entidades/{$afp}", ['nombre' => 'AFP Prueba Renombrada'], $this->auth())->assertOk();
        $this->deleteJson("/api/cursos-altura/entidades/{$afp}", [], $this->auth())->assertOk();

        $this->assertNotContains($afp, array_column($this->getJson('/api/cursos-altura/entidades', $this->auth())->json('data'), 'id'));
        $this->getJson("/api/cursos-altura/seguridad-social/{$id}", $this->auth())
            ->assertJsonPath('data.afp.nombre', 'AFP Prueba Renombrada')->assertJsonPath('data.afp.activo', 0);
        // Volver a guardar al trabajador con la desactivada no falla.
        $this->post("/api/cursos-altura/seguridad-social/{$id}", ['id_afp' => $afp], $this->auth())->assertOk();

        $fila = collect($this->getJson('/api/cursos-altura?per-page=500&s='.urlencode($this->operativo->documento ?? $this->operativo->nombre), $this->auth())->json('data.data'))->firstWhere('id_user', $id);
        $this->assertSame($afp, $fila['seguridad_social']['id_afp']);

        $this->postJson('/api/cursos-altura/entidades', ['tipo' => 'AFP', 'nombre' => 'AFP Prueba Renombrada'], $this->auth())
            ->assertOk()->assertJsonPath('data.id', $afp);
    }

    public function test_seguridad_social_solo_para_trabajadores_con_curso_y_conserva_el_documento(): void
    {
        Config::set('filesystems.uploads_disk', 'public');
        Storage::fake('public');
        $id = $this->operativo->id_user;
        $eps = $this->postJson('/api/cursos-altura/entidades', ['tipo' => 'EPS', 'nombre' => 'EPS Prueba Automática'], $this->auth())->json('data.id');
        $arl = $this->postJson('/api/cursos-altura/entidades', ['tipo' => 'ARL', 'nombre' => 'ARL Prueba Automática'], $this->auth())->json('data.id');

        $this->post("/api/cursos-altura/seguridad-social/{$id}", ['id_eps' => $eps], $this->auth())->assertUnprocessable();

        // Un curso vencido basta.
        $this->curso($id, '2020-01-01', '2021-01-01');

        // Una EPS no sirve como ARL.
        $this->post("/api/cursos-altura/seguridad-social/{$id}", ['id_arl' => $eps], $this->auth())
            ->assertUnprocessable()->assertJsonValidationErrors('id_arl');

        $ruta = $this->post("/api/cursos-altura/seguridad-social/{$id}", [
            'id_eps' => $eps,
            'documento_eps' => UploadedFile::fake()->create('eps.pdf', 100, 'application/pdf'),
        ], $this->auth())->assertOk()->json('data.ruta_eps');
        Storage::disk('public')->assertExists($ruta);

        $data = $this->post("/api/cursos-altura/seguridad-social/{$id}", ['id_eps' => $eps, 'id_arl' => $arl], $this->auth())->assertOk()->json('data');
        $this->assertSame($ruta, $data['ruta_eps']);
        $this->assertSame($arl, $data['id_arl']);
        $this->getJson("/api/cursos-altura/seguridad-social/{$id}", $this->auth())->assertOk()
            ->assertJsonPath('data.id_eps', $eps)->assertJsonPath('data.ruta_eps', $ruta)->assertJsonPath('data.id_arl', $arl);
    }

    /** Dos operativos sin curso previo, ahora con uno vigente desde hace un año. @return array{0: int, 1: int} */
    private function dosOperativosVigentes(): array
    {
        $ids = Usuario::where('estado', 'activo')
            ->whereIn('perfil', PermisosLicenciasServices::PERFILES_ASIGNABLES)
            ->whereNotIn('id_user', DB::table('cursos_altura')->select('id_user'))
            ->limit(2)->pluck('id_user')->all();

        if (count($ids) < 2) {
            $this->markTestSkipped('Faltan dos operativos sin curso.');
        }

        foreach ($ids as $id) {
            $this->curso($id, now()->subYear()->toDateString(), null);
        }

        return $ids;
    }

    /** @param  array<int, int>  $operativos */
    private function trabajo(array $operativos): int
    {
        $reporte = DB::table('reportes as rp')->join('inventario as iv', 'iv.id', '=', 'rp.id_inventario')
            ->whereNull('rp.id_reporte')
            ->whereNotIn('rp.id', DB::table('trabajos_altura')->select('id_reporte'))->value('rp.id');
        $id = DB::table('trabajos_altura')->insertGetId(['id_reporte' => $reporte, 'fecha_trabajo' => now()->toDateString()]);
        foreach ($operativos as $op) {
            DB::table('trabajos_altura_operativos')->insert(['id_trabajo' => $id, 'id_operativo' => $op]);
        }

        return $id;
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
