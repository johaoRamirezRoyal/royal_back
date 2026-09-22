<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Restaura dos otorgamientos que sus migraciones de seed originales ya intentaron dar al
 * perfil Admisiones (9) pero que en esta BD no están (confirmado contra `cron_permisos`
 * el 2026-09-22, mismo tipo de drift/resync de `insertGetId` documentado en CLAUDE.md
 * para Noticias e Instituciones):
 * - "Ver Instituciones y Documentos" (2026_08_31_190000_seed_opcion_ver_instituciones_documentos).
 * - "Gestión de Acudientes" (2026_09_02_120000_seed_opcion_gestion_acudientes).
 *
 * Busca por nombre, no por id — el id de cada opción lo asigna `insertGetId` al crearla y
 * puede no coincidir entre entornos, que es justo la causa raíz de este mismo problema.
 * Ver InstitucionAdminController::OPCION_LECTURA / AcudientesAdminController::OPCION_GESTION,
 * ambos corregidos en el mismo cambio para apuntar a los ids reales (111/112).
 */
return new class extends Migration
{
    private const ID_PERFIL_ADMISIONES = 9;

    private const OPCIONES = [
        'Ver Instituciones y Documentos',
        'Gestión de Acudientes',
    ];

    public function up(): void
    {
        foreach (self::OPCIONES as $nombreOpcion) {
            $idOpcion = DB::table('cron_opciones')->where('nombre', $nombreOpcion)->value('id');

            if (!$idOpcion) {
                continue;
            }

            $yaExiste = DB::table('cron_permisos')
                ->where('id_opcion', $idOpcion)
                ->where('id_perfil', self::ID_PERFIL_ADMISIONES)
                ->exists();

            if (!$yaExiste) {
                DB::table('cron_permisos')->insert([
                    'id_opcion' => $idOpcion,
                    'id_perfil' => self::ID_PERFIL_ADMISIONES,
                    'activo' => 1,
                    'fechareg' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $idsOpcion = DB::table('cron_opciones')->whereIn('nombre', self::OPCIONES)->pluck('id');

        DB::table('cron_permisos')
            ->whereIn('id_opcion', $idsOpcion)
            ->where('id_perfil', self::ID_PERFIL_ADMISIONES)
            ->delete();
    }
};
