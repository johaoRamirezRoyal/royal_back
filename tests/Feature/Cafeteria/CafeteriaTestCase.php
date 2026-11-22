<?php

namespace Tests\Feature\Cafeteria;

use App\Models\Usuarios\Usuario;
use App\Services\JwtService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Base de las pruebas de la cafetería: contra la base real del .env, dentro de una transacción
 * que se revierte al final (mismo patrón que OpcionesRolesTest). Requiere las migraciones
 * `2026_11_22_1000xx_*caf*` aplicadas.
 */
abstract class CafeteriaTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $env = file_get_contents(base_path('.env'));
        preg_match('/^DB_DATABASE=(.*)$/m', $env, $match);
        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql.database', trim($match[1] ?? 'laravel', " \t\n\r\0\x0B\"'"));
        DB::purge('mysql');

        if (! DB::getSchemaBuilder()->hasTable('caf_cafeterias')) {
            $this->markTestSkipped('Faltan las migraciones de la cafetería.');
        }

        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    protected function auth(Usuario $usuario): array
    {
        return ['Authorization' => 'Bearer ' . app(JwtService::class)->generateToken($usuario), 'Accept' => 'application/json'];
    }
}
