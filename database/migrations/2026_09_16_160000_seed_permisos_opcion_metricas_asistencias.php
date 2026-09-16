<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ID_OPCION = 146; // "Metricas Asistencias" (cron_opciones, id_modulo=3)
    // Mismo set que la 100 ("Configuración de asistencia", su opción hermana en el mismo
    // grupo del sidebar "Asistencia de Trabajadores"): Super Admin, Administrador, RRHH.
    private const PERFILES_CON_ACCESO = [1, 2, 8];

    /**
     * La opción 146 ya existía en `cron_opciones` (creada al cablear el
     * PermissionGate de /gestion-humana/metricas-asistencias) pero nunca se le otorgó a
     * ningún perfil en `cron_permisos` — quedó sin nadie con acceso, ni siquiera Super
     * Admin, y por eso no aparecía en el sidebar para nadie (isNavItemVisible/
     * PermissionGate son fail-closed).
     */
    public function up(): void
    {
        foreach (self::PERFILES_CON_ACCESO as $idPerfil) {
            $existe = DB::table('cron_permisos')
                ->where('id_opcion', self::ID_OPCION)
                ->where('id_perfil', $idPerfil)
                ->exists();

            if (!$existe) {
                DB::table('cron_permisos')->insert([
                    'id_opcion' => self::ID_OPCION,
                    'id_perfil' => $idPerfil,
                    'activo' => 1,
                    'fechareg' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('cron_permisos')
            ->where('id_opcion', self::ID_OPCION)
            ->whereIn('id_perfil', self::PERFILES_CON_ACCESO)
            ->delete();
    }
};
