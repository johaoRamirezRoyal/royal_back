<?php

namespace Tests\Feature\AdminManagement;

use App\Models\Usuarios\Usuario;
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
        DB::rollBack();
        parent::tearDown();
    }

    public function test_super_admin_con_correo_fuera_de_la_lista_recibe_403(): void
    {
        Config::set('adminmanagement.emails', ['otro@ejemplo.com']);

        $this->getJson('/api/admin-management/llaves-maestras', $this->auth())->assertForbidden();
        $this->postJson('/api/admin-management/llaves-maestras', ['id_user' => $this->admin->id_user], $this->auth())->assertForbidden();
        $this->deleteJson('/api/admin-management/llaves-maestras/1', [], $this->auth())->assertForbidden();
    }

    public function test_super_admin_con_correo_en_la_lista_entra(): void
    {
        Config::set('adminmanagement.emails', [mb_strtolower(trim($this->admin->correo))]);

        $this->getJson('/api/admin-management/llaves-maestras', $this->auth())->assertOk();
    }

    private function auth(): array
    {
        $token = app(JwtService::class)->generateToken($this->admin);

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }
}
