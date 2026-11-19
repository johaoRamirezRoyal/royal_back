<?php

namespace Tests\Feature\Auth;

use App\Enums\TipoPerfil;
use App\Models\Usuarios\DispositivoConfiable;
use App\Models\Usuarios\Perfil;
use App\Models\Usuarios\Usuario;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Login desde un equipo ya verificado de un acudiente creado en el módulo Acudientes (sin
 * `usuarios.user`): entra y no se intenta la sesión en SAMI. Antes daba 500 (TypeError en
 * SamiSsoService). Contra la base real, en una transacción (ver SesionesActivasTest).
 */
class LoginSinSamiTest extends TestCase
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

    public function test_acudiente_sin_usuario_de_sami_entra_desde_equipo_verificado(): void
    {
        Http::fake();

        $acudiente = Usuario::create([
            'documento' => '9999000097', 'nombre' => 'Prueba', 'apellido' => 'Sin Sami', 'correo' => 'sin.sami@prueba.test',
            'perfil' => Perfil::idParaCrear(TipoPerfil::ACUDIENTE), 'estado' => 'activo', 'pass' => 'ClavePrueba2026',
        ]);
        DispositivoConfiable::create([
            'id_user' => $acudiente->id_user, 'token_hash' => hash('sha256', 'equipo-de-prueba'),
            'nombre_dispositivo' => 'test', 'ip_registro' => '127.0.0.1', 'ultima_vez' => now(), 'created_at' => now(),
        ]);

        $this->withCredentials()->withUnencryptedCookie('device_token', 'equipo-de-prueba')
            ->postJson('/api/auth/login', ['user' => 'sin.sami@prueba.test', 'pass' => 'ClavePrueba2026'])
            ->assertOk();

        Http::assertNothingSent();
    }
}
