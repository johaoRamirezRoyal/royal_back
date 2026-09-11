<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La opción 114 "Compras — Generar solicitudes" pasa a gatear el grupo completo de
 * Compras en el sidebar/router (ver sideBar/index.layout.tsx y router/index.tsx), no solo
 * "Solicitudes". El perfil Ventas (34) tiene las opciones 104/105 (Seguimiento de compra)
 * pero no estaba en la lista original de la migración
 * seed_opcion_generar_solicitudes_compra — sin este grant perdería acceso a Seguimiento de
 * compra al quedar detrás del gate del módulo completo. Idempotente por
 * (id_opcion, id_perfil), igual patrón que grant_opciones_evaluaciones_a_coordinador.
 */
return new class extends Migration
{
    private const ID_OPCION = 114;
    private const ID_PERFIL_VENTAS = 34;

    public function up(): void
    {
        $existe = DB::table('cron_permisos')
            ->where('id_opcion', self::ID_OPCION)
            ->where('id_perfil', self::ID_PERFIL_VENTAS)
            ->exists();

        if (!$existe) {
            DB::table('cron_permisos')->insert([
                'id_opcion' => self::ID_OPCION,
                'id_perfil' => self::ID_PERFIL_VENTAS,
                'activo' => 1,
                'fechareg' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('cron_permisos')
            ->where('id_opcion', self::ID_OPCION)
            ->where('id_perfil', self::ID_PERFIL_VENTAS)
            ->delete();
    }
};
