<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Acceso de solo lectura a Instituciones y sus documentos — separada de "Gestión de
 * Instituciones" (opción 104, exclusiva de Super Admin, ver
 * 2026_08_31_130000_restrict_opcion_gestion_instituciones_a_super_admin). Otorgada al
 * perfil Admisiones (9): puede ver el listado de instituciones y los documentos que ha
 * subido cada una, pero no crear/editar/activar-desactivar instituciones ni tocar la
 * configuración — ver InstitucionAdminController::OPCION_LECTURA.
 */
return new class extends Migration
{
    private const NOMBRE_OPCION = 'Ver Instituciones y Documentos';
    private const ID_MODULO_ADMISIONES = 1;
    private const ID_PERFIL_ADMISIONES = 9;

    public function up(): void
    {
        $idOpcion = DB::table('cron_opciones')->insertGetId([
            'nombre' => self::NOMBRE_OPCION,
            'id_modulo' => self::ID_MODULO_ADMISIONES,
            'activo' => 1,
            'fechareg' => now(),
        ]);

        DB::table('cron_permisos')->insert([
            'id_opcion' => $idOpcion,
            'id_perfil' => self::ID_PERFIL_ADMISIONES,
            'activo' => 1,
            'fechareg' => now(),
        ]);
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
