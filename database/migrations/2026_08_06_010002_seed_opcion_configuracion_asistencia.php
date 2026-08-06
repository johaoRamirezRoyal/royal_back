<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NOMBRE_OPCION = 'Configuración de asistencia';
    private const ID_MODULO_GESTION_HUMANA = 3;
    // Super Admin, Administrador, Recursos humanos (verificado contra la tabla `perfiles`).
    private const PERFILES_CON_ACCESO = [1, 2, 8];

    /**
     * `cron_opciones`/`cron_permisos` no se seedean vía Eloquent en este repo (son
     * tablas legacy manejadas directo por SQL) — se inserta igual que cualquier otra
     * opción existente, vía DB::table().
     */
    public function up(): void
    {
        $idOpcion = DB::table('cron_opciones')->insertGetId([
            'nombre' => self::NOMBRE_OPCION,
            'id_modulo' => self::ID_MODULO_GESTION_HUMANA,
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
