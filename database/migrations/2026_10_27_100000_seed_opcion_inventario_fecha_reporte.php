<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Opción 140 "Fecha de Reporte" (módulo 2, Inventario, ver InventariosController): permite
 * elegir la fecha (`reportes.fechareg`) al reportar un daño. Sin ella, el reporte queda con
 * la fecha actual.
 *
 * No se otorga a ningún perfil: se asigna desde /permisos (Super Admin la tiene por su acceso
 * global). Id explícito (el de producción): si ya está ocupado por otra cosa, la migración
 * falla en vez de usar otro en silencio.
 */
return new class extends Migration
{
    private const ID_OPCION = 140;

    private const NOMBRE = 'Fecha de Reporte';

    private const ID_MODULO = 2;

    public function up(): void
    {
        $existente = DB::table('cron_opciones')->where('id', self::ID_OPCION)->value('nombre');

        if ($existente !== null && $existente !== self::NOMBRE) {
            throw new RuntimeException(sprintf(
                'cron_opciones.id %d ya está ocupado por "%s". Ajusta el id aquí, en App\Support\Permiso y en el frontend.',
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
