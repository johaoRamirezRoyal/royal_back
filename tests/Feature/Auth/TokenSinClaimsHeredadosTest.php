<?php

namespace Tests\Feature\Auth;

use App\Models\Usuarios\Usuario;
use App\Services\JwtService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/**
 * Un login normal en una petición que ya leyó otro token (JwtFromCookie lee la cookie anterior)
 * no hereda sus claims: antes salía marcado `via_llave_maestra` y daba 401 en cada request.
 * Contra la base real, en una transacción (ver SesionesActivasTest).
 */
class TokenSinClaimsHeredadosTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_el_token_nuevo_no_hereda_los_claims_de_la_llave_maestra_leida(): void
    {
        $usuario = Usuario::where('estado', 'activo')->first() ?? $this->markTestSkipped('Falta un usuario activo.');
        $jwt = app(JwtService::class);

        $deLlave = $jwt->generateToken($usuario, 'mysql', ['via_llave_maestra' => true, 'generado_por' => 1, 'llave_maestra_id' => 99]);
        JWTAuth::setToken($deLlave)->getPayload(); // lo que hace JwtFromCookie con la cookie vieja

        $payload = $jwt->getPayload($jwt->generateToken($usuario, 'mysql'));

        $this->assertArrayNotHasKey('via_llave_maestra', $payload);
        $this->assertArrayNotHasKey('generado_por', $payload);
        $this->assertArrayNotHasKey('llave_maestra_id', $payload);
        $this->assertArrayHasKey('sid', $payload);
    }
}
