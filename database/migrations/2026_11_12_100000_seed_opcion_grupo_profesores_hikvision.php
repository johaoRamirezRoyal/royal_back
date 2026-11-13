<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 3 de permisos por colegio: el grupo de Hikvision salía de un mapa por número de perfil
 * (hikvisionattendanceService::GROUP_ID_POR_PERFIL). Ahora sale del tipo de perfil, y esta
 * opción pone en el grupo Profesores (9) a perfiles que no son de tipo DOCENTE (en el Royal,
 * Deporte y Directivo Docente). Se otorga por NOMBRE del perfil, igual que
 * 2026_11_10_110000_seed_opciones_roles, para que nadie cambie de grupo ni de horario.
 */
return new class extends Migration
{
    private const ID = 171;
    private const NOMBRE = 'Asistencia — Grupo Profesores en las terminales';
    private const MODULO = 3; // Gestion Humana
    private const PERFILES = ['docente', 'docente ict', 'deporte', 'directivo docente'];

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
        DB::table('cron_opciones')->where('id', self::ID)->where('nombre', self::NOMBRE)->delete();
    }
};
