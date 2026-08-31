<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El módulo "Gestión de Instituciones" (opción 104, ver
 * 2026_08_31_110000_seed_opcion_gestion_instituciones) pasa a ser exclusivo de Super
 * Admin — se retira el acceso que tenía por defecto el perfil Admisiones (9).
 */
return new class extends Migration
{
    private const NOMBRE_OPCION = 'Gestión de Instituciones';

    private const ID_PERFIL_ADMISIONES = 9;

    public function up(): void
    {
        $idOpcion = DB::table('cron_opciones')->where('nombre', self::NOMBRE_OPCION)->value('id');

        if ($idOpcion) {
            DB::table('cron_permisos')
                ->where('id_opcion', $idOpcion)
                ->where('id_perfil', self::ID_PERFIL_ADMISIONES)
                ->delete();
        }
    }

    public function down(): void
    {
        $idOpcion = DB::table('cron_opciones')->where('nombre', self::NOMBRE_OPCION)->value('id');

        if ($idOpcion) {
            DB::table('cron_permisos')->insert([
                'id_opcion' => $idOpcion,
                'id_perfil' => self::ID_PERFIL_ADMISIONES,
                'activo' => 1,
                'fechareg' => now(),
            ]);
        }
    }
};
