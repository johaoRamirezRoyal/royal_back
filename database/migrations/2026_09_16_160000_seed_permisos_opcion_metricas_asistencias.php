<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // "Metricas Asistencias" (cron_opciones, id_modulo=3) — nació como id 146, pero ese
    // era un id inflado por 19 duplicados locales generados al re-correr migraciones
    // seed_opcion_* viejas; al sincronizar cron_opciones con producción (2026-09-16) se
    // renumeró a 127 (justo después del último id real de producción, 125). Esta
    // migración ya corrió con el valor 146 en local — se actualiza el valor acá para que
    // vuelva a ser idempotente si corre de nuevo en cualquier BD que ya tenga la 127.
    private const ID_OPCION = 127;
    // Mismo set que la 100 ("Configuración de asistencia", su opción hermana en el mismo
    // grupo del sidebar "Asistencia de Trabajadores"): Super Admin, Administrador, RRHH.
    private const PERFILES_CON_ACCESO = [1, 2, 8];

    /**
     * La opción ya existía en `cron_opciones` (creada al cablear el PermissionGate de
     * /gestion-humana/metricas-asistencias) pero nunca se le otorgó a ningún perfil en
     * `cron_permisos` — quedó sin nadie con acceso, ni siquiera Super Admin, y por eso no
     * aparecía en el sidebar para nadie (isNavItemVisible/PermissionGate son fail-closed).
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
