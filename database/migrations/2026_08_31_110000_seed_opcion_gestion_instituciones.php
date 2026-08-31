<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NOMBRE_OPCION = 'Gestión de Instituciones';
    private const ID_MODULO_ADMISIONES = 1;
    // Super Admin (1) y Admisiones (9) — mismos perfiles que ya administran el módulo de
    // Admisiones (ver opción 22); ajustable después desde /permisos.
    private const PERFILES_CON_ACCESO = [1, 9];

    /**
     * `cron_opciones`/`cron_permisos` no se seedean vía Eloquent en este repo (son
     * tablas legacy manejadas directo por SQL) — ver docs/sistema-permisos.md.
     */
    public function up(): void
    {
        $idOpcion = DB::table('cron_opciones')->insertGetId([
            'nombre' => self::NOMBRE_OPCION,
            'id_modulo' => self::ID_MODULO_ADMISIONES,
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
