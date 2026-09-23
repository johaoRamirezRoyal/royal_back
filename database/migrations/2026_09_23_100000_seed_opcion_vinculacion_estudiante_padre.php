<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Módulo "Vinculación Estudiante - Padre" (Académico): gestionar qué estudiantes (perfil
 * 16) están vinculados a cada acudiente (perfil 6) en `estudiantes_padres`. Opción propia
 * a propósito — la 73 ("Estudiantes", módulo legacy Matricula) ya existe pero está
 * otorgada al perfil Acudiente, que no debe poder vincular/desvincular. Solo Super Admin
 * por defecto; el resto se otorga desde /permisos.
 */
return new class extends Migration
{
    private const NOMBRE_OPCION = 'Vinculación Estudiante - Padre';

    private const ID_MODULO_ACADEMICO = 14;

    private const PERFILES_CON_ACCESO = [1];

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
