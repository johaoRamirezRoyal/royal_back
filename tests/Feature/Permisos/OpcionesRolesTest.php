<?php

namespace Tests\Feature\Permisos;

use App\Models\Usuarios\Usuario;
use App\Services\JwtService;
use App\Services\Usuarios\PermisosPerfil;
use App\Support\Permiso;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 3: lo que decidía un número de perfil (Coordinador 26, Administrador 2...) ahora es una
 * opción (155–160). Contra la base real, en una transacción que se revierte.
 */
class OpcionesRolesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $env = file_get_contents(base_path('.env'));
        preg_match('/^DB_DATABASE=(.*)$/m', $env, $match);
        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql.database', trim($match[1] ?? 'laravel', " \t\n\r\0\x0B\"'"));
        DB::purge('mysql');
        DB::beginTransaction();

        if (! DB::table('cron_opciones')->where('id', Permiso::COORDINACION_NIVEL)->exists()) {
            $this->markTestSkipped('Falta la migración 2026_11_10_110000_seed_opciones_roles.');
        }
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_super_admin_no_queda_encerrado_en_un_nivel(): void
    {
        $this->assertFalse(PermisosPerfil::perfilTiene(Permiso::PERFIL_SUPER_ADMIN, Permiso::COORDINACION_NIVEL));
        $this->assertFalse(PermisosPerfil::perfilTiene(Permiso::PERFIL_SUPER_ADMIN, Permiso::DIRECCION_ADMINISTRATIVA));
        $this->assertTrue(PermisosPerfil::perfilTiene(Permiso::PERFIL_SUPER_ADMIN, Permiso::COMPRAS_TODOS_LOS_NIVELES));
    }

    public function test_perfiles_con_y_perfil_tiene_siguen_a_cron_permisos(): void
    {
        $perfil = (int) DB::table('perfiles')->where('id_perfil', '<>', 1)
            ->whereNotIn('id_perfil', PermisosPerfil::perfilesCon(Permiso::COORDINACION_NIVEL))->value('id_perfil');

        $this->assertFalse(PermisosPerfil::perfilTiene($perfil, Permiso::COORDINACION_NIVEL));

        DB::table('cron_permisos')->insert(['id_opcion' => Permiso::COORDINACION_NIVEL, 'id_perfil' => $perfil, 'activo' => 1]);

        $this->assertContains($perfil, PermisosPerfil::perfilesCon(Permiso::COORDINACION_NIVEL));
        $this->assertTrue(PermisosPerfil::perfilTiene($perfil, Permiso::COORDINACION_NIVEL));
    }

    public function test_bandeja_de_compras_por_opcion_y_no_por_perfil(): void
    {
        $sinOpcion = Usuario::where('estado', 'activo')->where('perfil', '<>', 1)
            ->whereNotIn('perfil', PermisosPerfil::perfilesCon(Permiso::COORDINACION_NIVEL, Permiso::COMPRAS_TODOS_LOS_NIVELES))
            ->first() ?? $this->markTestSkipped('No hay un usuario sin las opciones 155/157.');

        $this->getJson('/api/solicitudes', $this->auth($sinOpcion))->assertForbidden();

        // Su perfil recibe la coordinación de nivel: entra, sin cambiar ningún número de perfil.
        DB::table('cron_permisos')->insert(['id_opcion' => Permiso::COORDINACION_NIVEL, 'id_perfil' => $sinOpcion->perfil, 'activo' => 1]);
        $this->assertNotSame(403, $this->getJson('/api/solicitudes', $this->auth($sinOpcion))->status());
    }

    public function test_entrar_a_admisiones_como_personal_es_por_opcion_y_no_por_nombre(): void
    {
        $sinOpcion = Usuario::where('estado', 'activo')->where('perfil', '<>', 1)
            ->whereNotIn('perfil', PermisosPerfil::perfilesCon(Permiso::ADMISIONES_GESTION_PERSONAL))
            ->whereNotNull('correo')->where('correo', '<>', 'rector@colegio.edu')
            ->first() ?? $this->markTestSkipped('No hay un usuario sin la opción 170.');

        $intercambiar = fn () => $this->withCredentials()->withUnencryptedCookie('token', app(JwtService::class)->generateToken($sinOpcion, 'mysql'))
            ->postJson('/api/auth/admissions/exchange-token');

        $intercambiar()->assertForbidden();

        DB::table('cron_permisos')->insert(['id_opcion' => Permiso::ADMISIONES_GESTION_PERSONAL, 'id_perfil' => $sinOpcion->perfil, 'activo' => 1]);
        $respuesta = $intercambiar()->assertOk();

        // El token de admisiones queda anclado a la base del colegio de quien entra.
        $admisiones = \Tymon\JWTAuth\Facades\JWTAuth::setToken($respuesta->getCookie('admissions_token', false)->getValue())->getPayload();
        $this->assertSame('mysql', $admisiones->get('db_connection'));
    }

    public function test_psicologa_de_admisiones_por_grado(): void
    {
        $this->assertSame(Permiso::ADMISIONES_PSICOLOGA_PREESCOLAR, \App\Services\Admisiones\AdmisionesServices::opcionPsicologaParaGrado('Kinder'));
        $this->assertSame(Permiso::ADMISIONES_PSICOLOGA_BACHILLERATO, \App\Services\Admisiones\AdmisionesServices::opcionPsicologaParaGrado('Décimo de bachillerato'));
        $this->assertNull(\App\Services\Admisiones\AdmisionesServices::opcionPsicologaParaGrado('Grado inexistente'));
    }

    public function test_grupo_de_hikvision_por_tipo_y_opcion_171(): void
    {
        $servicio = \App\Services\Hikvisionattendance\hikvisionattendanceService::class;
        $olvidarGrupos = fn () => (new \ReflectionProperty($servicio, 'gruposPorPerfil'))->setValue(null, []);
        $deTipo = fn (string $tipo) => (int) (DB::table('perfiles')->where('tipo', $tipo)->where('id_perfil', '<>', 1)
            ->whereNotIn('id_perfil', PermisosPerfil::perfilesCon(Permiso::HIKVISION_GRUPO_PROFESORES))->value('id_perfil')
            ?? $this->markTestSkipped("No hay un perfil {$tipo} sin la opción 171."));

        $administrativo = $deTipo('ADMINISTRATIVO');
        $olvidarGrupos();
        $this->assertSame($servicio::GRUPO_ADMINISTRATIVOS, $servicio::grupoDePerfil(Permiso::PERFIL_SUPER_ADMIN));
        $this->assertSame($servicio::GRUPO_ADMINISTRATIVOS, $servicio::grupoDePerfil($administrativo));
        $this->assertSame($servicio::GRUPO_ESTUDIANTES, $servicio::grupoDePerfil($deTipo('ESTUDIANTE')));
        $this->assertNull($servicio::grupoDePerfil($deTipo('ACUDIENTE')));

        // Con la 171 pasa a Profesores sin dejar de ser administrativo.
        DB::table('cron_permisos')->insert(['id_opcion' => Permiso::HIKVISION_GRUPO_PROFESORES, 'id_perfil' => $administrativo, 'activo' => 1]);
        $olvidarGrupos();
        $this->assertSame($servicio::GRUPO_PROFESORES, $servicio::grupoDePerfil($administrativo));

        // El override de la terminal va por grupo: "9:2" manda 2 y "8:" no manda grupo.
        Config::set('services.hikvision.group_ids', '9:2,8:');
        $aTerminal = new \ReflectionMethod($servicio, 'obtenerGroupIdPorPerfil');
        $instancia = (new \ReflectionClass($servicio))->newInstanceWithoutConstructor();
        $this->assertSame($servicio::GRUPO_ADMINISTRATIVOS, $aTerminal->invoke($instancia, $administrativo));
        $this->assertNull($aTerminal->invoke($instancia, $deTipo('ESTUDIANTE')));
        $olvidarGrupos();
    }

    private function auth(Usuario $usuario): array
    {
        return ['Authorization' => 'Bearer ' . app(JwtService::class)->generateToken($usuario), 'Accept' => 'application/json'];
    }
}
