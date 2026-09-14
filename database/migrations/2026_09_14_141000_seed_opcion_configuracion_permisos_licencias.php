<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permiso para administrar los catálogos de Permisos y Licencias (motivos, y los
 * catálogos "hijos" ley/personal/institucional): renombrar, activar/desactivar y
 * vincular cada ítem de ley/personal/institucional con su motivo general — ver
 * migración 2026_09_14_140000_add_id_motivo_to_permiso_catalogos. Distinto de 80/81/
 * 82/83/90/92 (todas sobre las SOLICITUDES); arranca acotado a Super Admin/Administrador.
 */
return new class extends Migration
{
    private const ID_MODULO_PERMISOS_LICENCIAS = 3;
    private const NOMBRE = 'Permisos y Licencias — Configuración de catálogos';
    private const PERFILES = [1, 2]; // Super Admin, Administrador

    public function up(): void
    {
        $idOpcion = DB::table('cron_opciones')->insertGetId([
            'nombre' => self::NOMBRE,
            'id_modulo' => self::ID_MODULO_PERMISOS_LICENCIAS,
            'activo' => 1,
            'fechareg' => now(),
        ]);

        foreach (self::PERFILES as $idPerfil) {
            DB::table('cron_permisos')->insert([
                'id_opcion' => $idOpcion,
                'id_perfil' => $idPerfil,
                'activo' => 1,
                'fechareg' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $idOpcion = DB::table('cron_opciones')->where('nombre', self::NOMBRE)->value('id');

        if ($idOpcion) {
            DB::table('cron_permisos')->where('id_opcion', $idOpcion)->delete();
            DB::table('cron_opciones')->where('id', $idOpcion)->delete();
        }
    }
};
