<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dos permisos nuevos bajo cron_modulos.id=6 ("Zonas", legacy — reusado solo
 * como agrupador, sus tablas zonas/areas_zona/chek_zonas/reportes_zonas no se
 * tocan, ver AGENTS.md "Áreas Comunes"): administración completa de
 * Bloques/Áreas Comunes vs. uso acotado (reportar/ver/programar mantenimiento
 * solo del bloque de su propio nivel) para asistentes de nivel/coordinadores.
 */
return new class extends Migration
{
    private const ID_MODULO_ZONAS = 6;
    private const NOMBRE_ADMIN = 'Administrador areas comunes';
    private const NOMBRE_USO = 'Uso areas comunes';
    private const PERFILES_ADMIN = [1, 2]; // Super Admin, Administrador
    private const PERFILES_USO = [11, 26]; // Asistente de nivel, Coordinador

    public function up(): void
    {
        $idAdmin = DB::table('cron_opciones')->insertGetId([
            'nombre' => self::NOMBRE_ADMIN,
            'id_modulo' => self::ID_MODULO_ZONAS,
            'activo' => 1,
            'fechareg' => now(),
        ]);

        $idUso = DB::table('cron_opciones')->insertGetId([
            'nombre' => self::NOMBRE_USO,
            'id_modulo' => self::ID_MODULO_ZONAS,
            'activo' => 1,
            'fechareg' => now(),
        ]);

        foreach (self::PERFILES_ADMIN as $idPerfil) {
            DB::table('cron_permisos')->insert([
                'id_opcion' => $idAdmin,
                'id_perfil' => $idPerfil,
                'activo' => 1,
                'fechareg' => now(),
            ]);
        }

        foreach (self::PERFILES_USO as $idPerfil) {
            DB::table('cron_permisos')->insert([
                'id_opcion' => $idUso,
                'id_perfil' => $idPerfil,
                'activo' => 1,
                'fechareg' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach ([self::NOMBRE_ADMIN, self::NOMBRE_USO] as $nombre) {
            $idOpcion = DB::table('cron_opciones')->where('nombre', $nombre)->value('id');

            if ($idOpcion) {
                DB::table('cron_permisos')->where('id_opcion', $idOpcion)->delete();
                DB::table('cron_opciones')->where('id', $idOpcion)->delete();
            }
        }
    }
};
