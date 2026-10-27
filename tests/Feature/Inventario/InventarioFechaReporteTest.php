<?php

namespace Tests\Feature\Inventario;

use App\Models\Usuarios\Usuario;
use App\Services\JwtService;
use App\Support\Permiso;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Contra la base real (ver InventarioHistorialFlowTest): elegir la fecha de un reporte de daño
 * exige la opción Fecha de Reporte. No escribe nada: la petición se rechaza antes de guardar.
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

    public function test_sin_opcion_fecha_de_reporte_no_puede_elegir_la_fecha_del_reporte(): void
    {
        $perfilesConFecha = DB::table('cron_permisos')->where('id_opcion', Permiso::INVENTARIO_FECHA_REPORTE)
            ->where('activo', 1)->pluck('id_perfil');

        $usuario = Usuario::where('estado', 'activo')
            ->where('perfil', '!=', Permiso::PERFIL_SUPER_ADMIN)
            ->whereNotIn('perfil', $perfilesConFecha)
            ->whereIn('perfil', DB::table('cron_permisos')->where('id_opcion', Permiso::INVENTARIO)->where('activo', 1)->select('id_perfil'))
            ->first();

        $item = DB::table('inventario')->whereNotIn('estado', [2, 5])->value('id');
        $anio = DB::table('anio_escolar')->value('id');
        $periodo = DB::table('periodos')->value('id');

        if (! $usuario || ! $item || ! $anio || ! $periodo) {
            $this->markTestSkipped('Faltan datos reales (usuario con Inventario sin Fecha de Reporte, ítem, año o periodo).');
        }

        $token = app(JwtService::class)->generateToken($usuario);
        $reportesAntes = DB::table('reportes')->where('id_inventario', $item)->count();

        $this->postJson('/api/inventario/reportar', [
            'ids' => [$item],
            'id_log' => $usuario->id_user,
            'descripcion' => 'Prueba automática: no debe guardarse',
            'id_anio' => $anio,
            'id_periodo' => $periodo,
            'fechareg' => '2020-01-01',
        ], ['Authorization' => "Bearer {$token}"])->assertForbidden();

        $this->assertSame($reportesAntes, DB::table('reportes')->where('id_inventario', $item)->count());
    }
}
