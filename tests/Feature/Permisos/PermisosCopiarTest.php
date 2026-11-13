<?php

namespace Tests\Feature\Permisos;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * permisos:copiar empareja perfiles por nombre entre dos colegios y solo agrega. Contra las bases
 * reales del .env (Royal y Hebreo), en transacciones que se revierten.
 */
class PermisosCopiarTest extends TestCase
{
    private const OPCION = 45; // "Listado maestro de documentos", una opción del catálogo común

    protected function setUp(): void
    {
        parent::setUp();

        $env = file_get_contents(base_path('.env'));
        foreach (['mysql' => 'DB_DATABASE', 'sami_hebreo' => 'DB_HEBREO_DATABASE'] as $connection => $variable) {
            preg_match("/^{$variable}=(.*)$/m", $env, $match);
            Config::set("database.connections.{$connection}.driver", 'mysql');
            Config::set("database.connections.{$connection}.database", trim($match[1] ?? '', " \t\n\r\0\x0B\"'"));
            DB::purge($connection);
        }

        try {
            DB::connection('sami_hebreo')->getPdo();
        } catch (\Throwable) {
            $this->markTestSkipped('No hay base del Hebreo en este entorno.');
        }

        DB::connection('mysql')->beginTransaction();
        DB::connection('sami_hebreo')->beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::connection('sami_hebreo')->rollBack();
        DB::connection('mysql')->rollBack();
        parent::tearDown();
    }

    public function test_otorga_por_nombre_sin_quitar_y_solo_con_aplicar(): void
    {
        $royal = DB::connection('mysql');
        $hebreo = DB::connection('sami_hebreo');

        // Un perfil con el mismo nombre en las dos bases (los ids pueden no coincidir).
        $nombre = $royal->table('perfiles')->where('id_perfil', '<>', 1)->pluck('nombre')
            ->first(fn ($n) => $hebreo->table('perfiles')->where('nombre', $n)->exists())
            ?? $this->markTestSkipped('Ningún perfil se llama igual en las dos bases.');
        $idRoyal = $royal->table('perfiles')->where('nombre', $nombre)->value('id_perfil');
        $idHebreo = $hebreo->table('perfiles')->where('nombre', $nombre)->value('id_perfil');

        $royal->table('cron_permisos')->updateOrInsert(['id_opcion' => self::OPCION, 'id_perfil' => $idRoyal], ['activo' => 1]);
        $hebreo->table('cron_permisos')->where('id_opcion', self::OPCION)->where('id_perfil', $idHebreo)->delete();
        // Algo que solo tiene el destino: no se toca.
        $propia = $hebreo->table('cron_opciones')->whereNotIn('id', $royal->table('cron_permisos')->where('id_perfil', $idRoyal)->pluck('id_opcion'))->value('id');
        $hebreo->table('cron_permisos')->updateOrInsert(['id_opcion' => $propia, 'id_perfil' => $idHebreo], ['activo' => 1]);

        $tiene = fn ($opcion) => $hebreo->table('cron_permisos')->where('id_opcion', $opcion)->where('id_perfil', $idHebreo)->where('activo', 1)->exists();

        $this->artisan('permisos:copiar', ['origen' => 'mysql', 'destino' => 'sami_hebreo'])->assertSuccessful();
        $this->assertFalse($tiene(self::OPCION), 'Sin --aplicar no debe escribir.');

        $this->artisan('permisos:copiar', ['origen' => 'mysql', 'destino' => 'sami_hebreo', '--aplicar' => true])->assertSuccessful();
        $this->assertTrue($tiene(self::OPCION));
        $this->assertTrue($tiene($propia), 'Nunca quita lo que el destino configuró.');
    }

    public function test_rechaza_bases_que_no_son_de_un_colegio(): void
    {
        $this->artisan('permisos:copiar', ['origen' => 'mysql', 'destino' => 'admin_management'])->assertFailed();
        $this->artisan('permisos:copiar', ['origen' => 'mysql', 'destino' => 'mysql'])->assertFailed();
    }
}
