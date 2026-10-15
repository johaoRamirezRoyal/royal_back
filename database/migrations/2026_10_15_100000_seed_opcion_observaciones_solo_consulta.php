<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Opción 135 "Observaciones — solo consulta" del módulo Gestión Convivencial (15): ve las
 * observaciones de todos los profesores y no puede registrar, editar ni eliminar, aunque
 * también sea docente o tenga la 133/99 (ver ObservacionEstudianteController).
 *
 * No se otorga a ningún perfil: se asigna desde /permisos.
 * Id explícito, mismo motivo que 2026_10_14_100100_seed_opcion_observaciones_estudiantes.
 */
return new class extends Migration
{
    private const ID_OPCION = 135;

    private const NOMBRE = 'Observaciones — solo consulta';

    private const ID_MODULO = 15;

    public function up(): void
    {
        $existente = DB::table('cron_opciones')->where('id', self::ID_OPCION)->value('nombre');

        if ($existente !== null && $existente !== self::NOMBRE) {
            throw new RuntimeException(sprintf(
                'cron_opciones.id %d ya está ocupado por "%s". Ajusta el id aquí, en ObservacionEstudianteController y en el frontend (PermissionGate/sidebar).',
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
