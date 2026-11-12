<?php

namespace Tests\Feature\Permisos;

use App\Enums\TipoPerfil;
use App\Models\Usuarios\Perfil;
use App\Http\Middleware\RequirePermiso;
use App\Http\Resources\Usuarios\UsuarioResource;
use App\Models\Usuarios\Usuario;
use App\Services\JwtService;
use App\Support\Permiso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * perfiles.tipo: se cambia en PUT /permisos/perfiles/{id}/tipo (opción 28, nunca el Super
 * Admin) y viaja con la sesión como perfil_tipo. Contra la base real, en una transacción.
 */
class TipoPerfilTest extends TestCase
{
    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $env = file_get_contents(base_path('.env'));
        preg_match('/^DB_DATABASE=(.*)$/m', $env, $match);
        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql.database', trim($match[1] ?? 'laravel', " \t\n\r\0\x0B\"'"));
        DB::purge('mysql');
        DB::beginTransaction();

        $admin = Usuario::where('estado', 'activo')->where('perfil', Permiso::PERFIL_SUPER_ADMIN)->first();

        if (! $admin || ! DB::getSchemaBuilder()->hasColumn('perfiles', 'tipo')) {
            $this->markTestSkipped('Falta un Super Admin activo o la migración de perfiles.tipo.');
        }

        $this->admin = $admin;
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_cambia_el_tipo_y_lo_lista_en_permisos(): void
    {
        $idPerfil = (int) DB::table('perfiles')->where('id_perfil', '<>', 1)->value('id_perfil');

        $this->putJson("/api/permisos/perfiles/{$idPerfil}/tipo", ['tipo' => 'OPERATIVO'], $this->auth())
            ->assertOk()
            ->assertJsonPath('data.tipo', 'OPERATIVO');

        $this->assertSame('OPERATIVO', DB::table('perfiles')->where('id_perfil', $idPerfil)->value('tipo'));

        $listado = $this->postJson('/api/permisos/opciones-perfil', ['perfiles' => [$idPerfil]], $this->auth())
            ->assertOk()->json('data.0.perfil');
        $this->assertSame('OPERATIVO', $listado['tipo']);
    }

    public function test_rechaza_tipo_invalido_y_super_admin(): void
    {
        $idPerfil = (int) DB::table('perfiles')->where('id_perfil', '<>', 1)->value('id_perfil');

        $this->putJson("/api/permisos/perfiles/{$idPerfil}/tipo", ['tipo' => 'RECTOR'], $this->auth())
            ->assertUnprocessable();
        $this->putJson('/api/permisos/perfiles/1/tipo', ['tipo' => 'DOCENTE'], $this->auth())
            ->assertUnprocessable();
    }

    public function test_sin_la_opcion_28_recibe_403(): void
    {
        $sinPermiso = Usuario::where('estado', 'activo')
            ->where('perfil', '<>', 1)
            ->whereNotIn('perfil', DB::table('cron_permisos')->where('id_opcion', Permiso::PERMISOS_SISTEMA)->where('activo', 1)->select('id_perfil'))
            ->first();

        if (! $sinPermiso) {
            $this->markTestSkipped('No hay un usuario activo sin la opción 28.');
        }

        $token = app(JwtService::class)->generateToken($sinPermiso);
        $this->putJson("/api/permisos/perfiles/{$sinPermiso->perfil}/tipo", ['tipo' => 'DOCENTE'], [
            'Authorization' => "Bearer {$token}", 'Accept' => 'application/json',
        ])->assertForbidden();
    }

    public function test_el_middleware_deja_entrar_por_tipo(): void
    {
        $usuario = Usuario::where('estado', 'activo')
            ->where('perfil', '<>', Permiso::PERFIL_SUPER_ADMIN)
            ->whereNotIn('perfil', Perfil::idsDeTipo(TipoPerfil::DOCENTE))
            ->first() ?? $this->markTestSkipped('No hay un usuario activo que no sea docente.');

        $pasa = function (Usuario $u): int {
            $request = Request::create('/');
            $request->setUserResolver(fn () => $u);

            return (new RequirePermiso)->handle($request, fn () => response('ok'), 'tipo:DOCENTE')->getStatusCode();
        };

        $this->assertSame('permiso:99,tipo:DOCENTE', Permiso::ruta([99], [TipoPerfil::DOCENTE]));
        $this->assertSame(403, $pasa($usuario));

        // Su perfil pasa a ser de tipo docente: entra, sin cambiar ningún número de perfil.
        DB::table('perfiles')->where('id_perfil', $usuario->perfil)->update(['tipo' => 'DOCENTE']);
        $this->assertSame(200, $pasa($usuario->fresh()));
    }

    public function test_usuarios_paginados_filtra_por_tipo(): void
    {
        $filas = $this->getJson('/api/usuarios/paginados?tipos[]=ESTUDIANTE&per-page=20', $this->auth())
            ->assertOk()->json('data.data');

        if (! $filas) {
            $this->markTestSkipped('No hay estudiantes.');
        }
        $this->assertSame(['ESTUDIANTE'], array_values(array_unique(array_column(array_column($filas, 'perfil_relacion'), 'tipo'))));

        // Un tipo sin perfiles no se convierte en "sin filtro".
        DB::table('perfiles')->where('tipo', 'EGRESADO')->update(['tipo' => 'ADMINISTRATIVO']);
        $this->assertSame([], $this->getJson('/api/usuarios/paginados?tipos[]=EGRESADO', $this->auth())->assertOk()->json('data.data'));
    }

    public function test_id_para_crear_toma_el_menor_perfil_del_tipo(): void
    {
        $ids = Perfil::idsDeTipo(TipoPerfil::ESTUDIANTE) ?: $this->markTestSkipped('No hay perfil de estudiante.');

        $this->assertSame(min($ids), Perfil::idParaCrear(TipoPerfil::ESTUDIANTE));
    }

    public function test_la_sesion_lleva_perfil_tipo(): void
    {
        $docente = Usuario::where('estado', 'activo')->whereIn('perfil', Perfil::idsDeTipo(TipoPerfil::DOCENTE))->first()
            ?? $this->markTestSkipped('No hay un docente activo.');

        $datos = (new UsuarioResource($docente->load('perfilRelacion')))->toArray(Request::create('/'));

        $this->assertSame('DOCENTE', $datos['perfil_tipo']);
    }

    private function auth(): array
    {
        $token = app(JwtService::class)->generateToken($this->admin);

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }
}
