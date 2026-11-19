<?php

namespace Tests\Feature\AdminManagement;

use App\Models\Usuarios\Usuario;
use App\Services\AdminManagement\LlaveMaestraService;
use App\Services\JwtService;
use App\Support\Permiso;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Las llaves maestras exigen Super Admin **y** un correo de ADMIN_MANAGEMENT_EMAILS.
 * Contra la base real (ver CursosAlturaTest), dentro de una transacción que se revierte.
 */
class LlaveMaestraAccesoTest extends TestCase
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
        DB::connection('admin_management')->beginTransaction();

        $admin = Usuario::where('estado', 'activo')
            ->where('perfil', Permiso::PERFIL_SUPER_ADMIN)
            ->whereNotNull('correo')->where('correo', '<>', '')
            ->first();

        if (! $admin) {
            $this->markTestSkipped('Falta un Super Admin activo con correo.');
        }

        $this->admin = $admin;
    }

    protected function tearDown(): void
    {
        DB::connection('admin_management')->rollBack();
        DB::rollBack();
        parent::tearDown();
    }

    public function test_super_admin_con_correo_fuera_de_la_lista_recibe_403(): void
    {
        Config::set('adminmanagement.emails', ['otro@ejemplo.com']);

        $this->getJson('/api/admin-management/llaves-maestras', $this->auth())->assertForbidden();
        $this->getJson('/api/admin-management/llaves-maestras/sesiones', $this->auth())->assertForbidden();
        $this->postJson('/api/admin-management/llaves-maestras', ['id_user' => $this->admin->id_user], $this->auth())->assertForbidden();
        $this->deleteJson('/api/admin-management/llaves-maestras/1', [], $this->auth())->assertForbidden();
    }

    public function test_super_admin_con_correo_en_la_lista_entra(): void
    {
        Config::set('adminmanagement.emails', [mb_strtolower(trim($this->admin->correo))]);

        $this->getJson('/api/admin-management/llaves-maestras', $this->auth())->assertOk();
    }

    public function test_sesiones_lista_la_sesion_canjeada_hasta_que_se_cierra(): void
    {
        Config::set('adminmanagement.emails', [mb_strtolower(trim($this->admin->correo))]);
        $service = app(LlaveMaestraService::class);

        $generada = $service->generar($this->admin->id_user, 'mysql', $this->admin->id_user);
        $this->assertIsArray($service->redimir($generada['key'], '127.0.0.1'));

        $correos = fn () => collect($this->getJson('/api/admin-management/llaves-maestras/sesiones', $this->auth())
            ->assertOk()->json('data'))->pluck('usuario_objetivo.correo');

        $this->assertContains($this->admin->correo, $correos());

        $service->cerrarSesion('mysql', $this->admin->id_user);

        $this->assertNotContains($this->admin->correo, $correos());
    }

    public function test_cerrar_la_sesion_corta_el_token_y_otra_llave_no_lo_revive(): void
    {
        Config::set('adminmanagement.emails', [mb_strtolower(trim($this->admin->correo))]);
        $service = app(LlaveMaestraService::class);
        $url = '/api/admin-management/llaves-maestras';

        $primera = $this->canjear($service);
        $this->getJson($url, $primera['headers'])->assertOk();

        $this->nuevaPeticion();
        $this->deleteJson("{$url}/sesiones/{$primera['llave_id']}", [], $this->auth())->assertOk();
        // Primero por ?token= (el guard también lo acepta): no debe saltarse el corte.
        $this->nuevaPeticion();
        $this->getJson("{$url}?token={$primera['token']}", ['Accept' => 'application/json'])->assertUnauthorized();
        $this->nuevaPeticion();
        $this->getJson($url, $primera['headers'])->assertUnauthorized();

        $segunda = $this->canjear($service);
        $this->nuevaPeticion();
        $this->getJson($url, $segunda['headers'])->assertOk();
        $this->nuevaPeticion();
        $this->getJson($url, $primera['headers'])->assertUnauthorized();

        $this->nuevaPeticion();
        $this->deleteJson("{$url}/sesiones/{$primera['llave_id']}", [], $this->auth())->assertNotFound();
    }

    /**
     * En producción cada petición arranca de cero; en el test el guard y tymon.jwt guardan el
     * token anterior, y su factory los claims del último token generado.
     */
    private function nuevaPeticion(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();
        $this->app['tymon.jwt.auth']->factory()->emptyClaims();
    }

    /** Canjea una llave para el admin y arma el token igual que AuthController::redeemMasterKey. */
    private function canjear(LlaveMaestraService $service): array
    {
        $generada = $service->generar($this->admin->id_user, 'mysql', $this->admin->id_user);
        $resultado = $service->redimir($generada['key'], '127.0.0.1');
        $this->assertIsArray($resultado);

        $token = app(JwtService::class)->generateToken($this->admin, 'mysql', [
            'via_llave_maestra' => true,
            'generado_por' => $resultado['generado_por'],
            'llave_maestra_id' => $resultado['llave_id'],
        ]);

        return [
            'llave_id' => $resultado['llave_id'],
            'token' => $token,
            'headers' => ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'],
        ];
    }

    private function auth(): array
    {
        $token = app(JwtService::class)->generateToken($this->admin);

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }
}
