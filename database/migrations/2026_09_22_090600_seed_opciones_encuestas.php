<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Opciones de permiso del módulo Encuestas (QR por salón) — ver plan de la
 * funcionalidad. Mismo split administrar/ver-resultados que Evaluaciones
 * (2026_09_14_150000_seed_opciones_evaluaciones), módulo 3 = Gestión Humana.
 * Otorga de una vez en cron_permisos a Super Admin/Administrador/Gestión Humana —
 * NO dejarlo para una migración aparte, ver el incidente de "Metricas Asistencias"
 * en AGENTS.md (opción creada pero nunca otorgada, invisible para todos).
 */
return new class extends Migration
{
    private const ID_MODULO_GESTION_HUMANA = 3;

    private const NOMBRE_ADMIN = 'Encuestas — Administrar encuestas y salones';
    private const NOMBRE_VER = 'Encuestas — Ver resultados';

    private const PERFILES_CON_ACCESO = [1, 2, 8]; // Super Admin, Administrador, Gestión Humana

    public function up(): void
    {
        $idAdmin = DB::table('cron_opciones')->insertGetId([
            'nombre' => self::NOMBRE_ADMIN,
            'id_modulo' => self::ID_MODULO_GESTION_HUMANA,
            'activo' => 1,
            'fechareg' => now(),
        ]);

        $idVer = DB::table('cron_opciones')->insertGetId([
            'nombre' => self::NOMBRE_VER,
            'id_modulo' => self::ID_MODULO_GESTION_HUMANA,
            'activo' => 1,
            'fechareg' => now(),
        ]);

        foreach ([$idAdmin, $idVer] as $idOpcion) {
            foreach (self::PERFILES_CON_ACCESO as $idPerfil) {
                DB::table('cron_permisos')->insert([
                    'id_opcion' => $idOpcion,
                    'id_perfil' => $idPerfil,
                    'activo' => 1,
                    'fechareg' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('cron_opciones')
            ->whereIn('nombre', [self::NOMBRE_ADMIN, self::NOMBRE_VER])
            ->pluck('id');

        DB::table('cron_permisos')->whereIn('id_opcion', $ids)->delete();
        DB::table('cron_opciones')->whereIn('id', $ids)->delete();
    }
};
