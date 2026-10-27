<?php

namespace Tests\Feature\Inventario;

use App\Models\Usuarios\Usuario;
use App\Services\JwtService;
use App\Support\Permiso;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Contra la base real (ver InventarioHistorialFlowTest): editar `fechareg` exige la opción
 * Fecha Reporte además de Inventario. No escribe nada: la petición se rechaza antes de guardar.
 */
class InventarioFechaReporteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $env = file_get_contents(base_path('.env'));
        preg_match('/^DB_DATABASE=(.*)$/m', $env, $match);
        $baseDeDatosReal = trim($match[1] ?? 'laravel', " \t\n\r\0\x0B\"'");

        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql.database', $baseDeDatosReal);
        DB::purge('mysql');
    }

    public function test_sin_opcion_fecha_reporte_no_puede_cambiar_fechareg(): void
    {
        $perfilesConFecha = DB::table('cron_permisos')->where('id_opcion', Permiso::INVENTARIO_FECHA_REPORTE)
            ->where('activo', 1)->pluck('id_perfil');

        $usuario = Usuario::where('estado', 'activo')
            ->where('perfil', '!=', Permiso::PERFIL_SUPER_ADMIN)
            ->whereNotIn('perfil', $perfilesConFecha)
            ->whereIn('perfil', DB::table('cron_permisos')->where('id_opcion', Permiso::INVENTARIO)->where('activo', 1)->select('id_perfil'))
            ->first();

        $item = DB::table('inventario')->value('id');

        if (! $usuario || ! $item) {
            $this->markTestSkipped('No hay un usuario con Inventario y sin Fecha Reporte, o no hay ítems.');
        }

        $token = app(JwtService::class)->generateToken($usuario);

        $this->putJson("/api/inventario/{$item}", ['fechareg' => '2020-01-01'], ['Authorization' => "Bearer {$token}"])
            ->assertForbidden();

        $this->assertNotSame('2020-01-01', substr((string) DB::table('inventario')->where('id', $item)->value('fechareg'), 0, 10));
    }
}
