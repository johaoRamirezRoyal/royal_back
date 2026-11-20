<?php

namespace Tests\Feature\Auth;

use App\Models\Usuarios\Usuario;
use App\Services\Auth\SesionesActivasService;
use App\Services\JwtService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Máximo de dispositivos con la misma cuenta abierta: al entrar en uno de más se cierra la
 * sesión más antigua. Contra la base real (ver LlaveMaestraAccesoTest), en una transacción.
 */
class SesionesActivasTest extends TestCase
{
    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $env = file_get_contents(base_path('.env'));
        preg_match('/^DB_DATABASE=(.*)$/m', $env, $match);
        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql.database', trim($match[1] ?? 'laravel', " \t\n\r\0\x0B\"'"));
        DB::purge('mysql');
        DB::beginTransaction();

        $usuario = Usuario::where('estado', 'activo')->first();

        if (! $usuario) {
            $this->markTestSkipped('Falta un usuario activo.');
        }

        $this->usuario = $usuario;
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_el_dispositivo_de_mas_cierra_la_sesion_mas_antigua_y_el_logout_libera_lugar(): void
    {
        $tokens = [];
        for ($i = 0; $i <= SesionesActivasService::MAXIMO; $i++) {
            $tokens[] = $this->login();
        }

        $this->assertSame(401, $this->check($tokens[0]));
        foreach (array_slice($tokens, 1) as $token) {
            $this->assertSame(200, $this->check($token));
        }

        // El logout del más nuevo libera su lugar: un login más ya no cierra el más antiguo vivo.
        $this->nuevaPeticion();
        // withCredentials: sin él, las peticiones JSON del test no mandan cookies.
        $this->withCredentials()->withUnencryptedCookie('token', $tokens[4])->postJson('/api/auth/logout')->assertOk();
        $this->withCredentials = false;
        $this->unencryptedCookies = [];
        $this->login();

        $this->assertSame(200, $this->check($tokens[1]));
    }

    public function test_el_token_solo_se_acepta_en_el_header(): void
    {
        $token = $this->login();

        $this->assertSame(200, $this->check($token));

        $this->nuevaPeticion();
        // También cubre el campo `token` del cuerpo: InputSource lee query y cuerpo.
        $this->getJson("/api/auth/check?system=general&token={$token}")->assertUnauthorized();
    }

    private function login(): string
    {
        $this->nuevaPeticion();

        return app(JwtService::class)->generateToken($this->usuario, 'mysql');
    }

    private function check(string $token): int
    {
        $this->nuevaPeticion();

        return $this->getJson('/api/auth/check?system=general', ['Authorization' => "Bearer {$token}"])->status();
    }

    /** En producción cada petición arranca de cero; en el test tymon guarda token y claims. */
    private function nuevaPeticion(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();
        $this->app['tymon.jwt.auth']->factory()->emptyClaims();
    }
}
