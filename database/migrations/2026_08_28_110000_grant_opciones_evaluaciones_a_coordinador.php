<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ID_OPCION_VER = 102;
    private const ID_OPCION_RESPONDER = 103;
    private const ID_PERFIL_COORDINADOR = 26;

    /**
     * Las opciones 101/102/103 de Evaluaciones ya existen en `cron_opciones` (creadas a
     * mano el 2026-08-21) pero solo Super Admin las tiene otorgadas — el Coordinador
     * necesita 102 (ver sus propias evaluaciones/respuestas) y 103 (responder) para que
     * el flujo de "realizar evaluaciones" funcione en producción.
     */
    public function up(): void
    {
        foreach ([self::ID_OPCION_VER, self::ID_OPCION_RESPONDER] as $idOpcion) {
            $yaOtorgado = DB::table('cron_permisos')
                ->where('id_opcion', $idOpcion)
                ->where('id_perfil', self::ID_PERFIL_COORDINADOR)
                ->exists();

            if (!$yaOtorgado) {
                DB::table('cron_permisos')->insert([
                    'id_opcion' => $idOpcion,
                    'id_perfil' => self::ID_PERFIL_COORDINADOR,
                    'activo' => 1,
                    'fechareg' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('cron_permisos')
            ->whereIn('id_opcion', [self::ID_OPCION_VER, self::ID_OPCION_RESPONDER])
            ->where('id_perfil', self::ID_PERFIL_COORDINADOR)
            ->delete();
    }
};
