<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Módulo administrativo nuevo para gestionar `anio_escolar`/`periodos` (crear/activar años
 * escolares, crear/editar/activar/marcar-en-curso periodos) — antes solo existía CRUD de
 * años escolares dentro de Gestión Académica (opción 99) y lectura de periodos en
 * Evaluaciones; se pidió una opción propia, independiente de esas dos, para no atar el
 * acceso a este módulo al de Gestión Académica. Otorgada por defecto solo a Super Admin y
 * Administrador — es configuración institucional sensible (afecta reportes/evaluaciones/
 * asistencia que dependen del año y periodo vigentes).
 */
return new class extends Migration
{
    private const NOMBRE_OPCION = 'Año Escolar y Periodos';

    private const ID_MODULO_ACADEMICO = 14;

    private const PERFILES_CON_ACCESO = [1, 2];

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
