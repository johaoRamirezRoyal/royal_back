<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NOMBRE_OPCION = 'Llegadas tarde (Recepción)';
    private const ID_MODULO_ACADEMICO = 14;
    // Recepción: acceso operativo únicamente al listado de llegadas tarde (no al resto de
    // Gestión Académica, que sigue detrás de la opción 99). Las restricciones de este
    // perfil (solo hoy, sin eliminar, sin dashboard, sin configuración) se aplican
    // server-side en LlegadasTardeController/LlegadasTardeConfigController.
    private const PERFILES_CON_ACCESO = [33];

    /**
     * `cron_opciones`/`cron_permisos` no se seedean vía Eloquent en este repo (son
     * tablas legacy manejadas directo por SQL) — se inserta igual que cualquier otra
     * opción existente, vía DB::table().
     */
    public function up(): void
    {
        $idOpcion = DB::table('cron_opciones')->insertGetId([
            'nombre' => self::NOMBRE_OPCION,
            'id_modulo' => self::ID_MODULO_ACADEMICO,
            'activo' => 1,
            'fechareg' => now(),
        ]);

        foreach (self::PERFILES_CON_ACCESO as $idPerfil) {
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
        $idOpcion = DB::table('cron_opciones')->where('nombre', self::NOMBRE_OPCION)->value('id');

        if ($idOpcion) {
            DB::table('cron_permisos')->where('id_opcion', $idOpcion)->delete();
            DB::table('cron_opciones')->where('id', $idOpcion)->delete();
        }
    }
};
