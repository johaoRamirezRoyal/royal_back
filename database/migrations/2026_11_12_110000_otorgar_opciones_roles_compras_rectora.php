<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 3 de permisos por colegio. El código viejo comparaba los números de perfil del Royal en
 * todas las bases, así que en el Hebreo "Compras" (26) actuaba como Coordinador (aprobaba las
 * compras de su nivel) y "Rectora" (24) como psicóloga de primaria. Con las opciones esos
 * accidentes desaparecen; a pedido, se les otorga por NOMBRE lo que sí deben tener:
 * Compras 155 y 157, Rectora 157 y 161. En colegios sin esos perfiles (el Royal) no hace nada.
 */
return new class extends Migration
{
    /** nombre normalizado del perfil => opciones */
    private const OTORGAR = [
        'compras' => [155, 157],
        'rectora' => [157, 161],
    ];

    public function up(): void
    {
        $perfiles = DB::table('perfiles')->pluck('nombre', 'id_perfil')
            ->map(fn ($n) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $n))));

        foreach ($perfiles as $idPerfil => $nombre) {
            foreach (self::OTORGAR[$nombre] ?? [] as $opcion) {
                if (! DB::table('cron_opciones')->where('id', $opcion)->exists()) {
                    throw new RuntimeException("Falta la opción {$opcion}: corre antes 2026_11_10_110000 y 2026_11_10_120000.");
                }
                $existe = DB::table('cron_permisos')->where('id_opcion', $opcion)->where('id_perfil', $idPerfil);
                if ($existe->exists()) {
                    $existe->update(['activo' => 1]);
                } else {
                    DB::table('cron_permisos')->insert([
                        'id_opcion' => $opcion, 'id_perfil' => $idPerfil, 'activo' => 1, 'fechareg' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $perfiles = DB::table('perfiles')->pluck('nombre', 'id_perfil')
            ->map(fn ($n) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $n))));

        foreach ($perfiles as $idPerfil => $nombre) {
            if (isset(self::OTORGAR[$nombre])) {
                DB::table('cron_permisos')->where('id_perfil', $idPerfil)->whereIn('id_opcion', self::OTORGAR[$nombre])->delete();
            }
        }
    }
};
