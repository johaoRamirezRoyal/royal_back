<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fotos del curso: publicar es autoservicio del docente en los cursos de su carga (o que
 * dirige), sin opción. Esta opción es la de moderar: ver y borrar las publicaciones de
 * cualquier curso. Se otorga por NOMBRE del perfil, igual que 2026_11_10_110000_seed_opciones_roles.
 */
return new class extends Migration
{
    private const ID = 174;
    private const NOMBRE = 'Fotos del curso — Ver y borrar las de todos los cursos';
    private const MODULO = 14; // Gestión Académica
    private const PERFILES = ['coordinador', 'vicerrector', 'directivo docente'];

    public function up(): void
    {
        $existente = DB::table('cron_opciones')->where('id', self::ID)->value('nombre');

        if ($existente !== null && $existente !== self::NOMBRE) {
            throw new RuntimeException('cron_opciones.id ' . self::ID . " ya está ocupado por \"{$existente}\". Ajusta el id aquí y en App\\Support\\Permiso.");
        }

        if ($existente === null) {
            DB::table('cron_opciones')->insert([
                'id' => self::ID, 'nombre' => self::NOMBRE, 'id_modulo' => self::MODULO, 'activo' => 1, 'fechareg' => now(),
            ]);
        }

        $perfiles = DB::table('perfiles')->pluck('nombre', 'id_perfil')
            ->map(fn ($n) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $n))));

        foreach ($perfiles as $idPerfil => $nombre) {
            $yaLaTiene = DB::table('cron_permisos')->where('id_opcion', self::ID)->where('id_perfil', $idPerfil)->exists();

            if (in_array($nombre, self::PERFILES, true) && ! $yaLaTiene) {
                DB::table('cron_permisos')->insert([
                    'id_opcion' => self::ID, 'id_perfil' => $idPerfil, 'activo' => 1, 'fechareg' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('cron_permisos')->where('id_opcion', self::ID)->delete();
        DB::table('cron_opciones')->where('id', self::ID)->delete();
    }
};
