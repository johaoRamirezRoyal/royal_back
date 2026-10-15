<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Opción 138 "Ver actas de mis estudiantes" (módulo 15 Gestión Convivencial): solo lectura de
 * las actas de los estudiantes que hoy están en los cursos donde el usuario tiene carga
 * académica activa (ActasEstudianteController::sinLectura). Con la 136/137 se ve todo.
 *
 * No se otorga a ningún perfil: se asigna desde /permisos. Id explícito, mismo motivo que
 * 2026_10_15_100100_seed_opciones_actas_estudiante.
 */
return new class extends Migration
{
    private const ID_OPCION = 138;

    private const NOMBRE = 'Ver actas de mis estudiantes';

    private const ID_MODULO = 15;

    public function up(): void
    {
        $existente = DB::table('cron_opciones')->where('id', self::ID_OPCION)->value('nombre');

        if ($existente !== null && $existente !== self::NOMBRE) {
            throw new RuntimeException(sprintf(
                'cron_opciones.id %d ya está ocupado por "%s". Ajusta el id aquí, en ActasEstudianteController y en el frontend (PermissionGate/sidebar).',
                self::ID_OPCION,
                $existente,
            ));
        }

        if ($existente === null) {
            DB::table('cron_opciones')->insert([
                'id' => self::ID_OPCION,
                'nombre' => self::NOMBRE,
                'id_modulo' => self::ID_MODULO,
                'activo' => 1,
                'fechareg' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('cron_permisos')->where('id_opcion', self::ID_OPCION)->delete();
        DB::table('cron_opciones')->where('id', self::ID_OPCION)->where('nombre', self::NOMBRE)->delete();
    }
};
