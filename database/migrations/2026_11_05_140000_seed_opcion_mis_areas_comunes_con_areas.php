<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Opción 149 (módulo 6, Zonas): complemento de la 119 "Mis areas comunes"
 * (/inventario/areas-comunes/mis-areas). Con solo la 119 el responsable de un bloque ve lo
 * asignado directo al bloque; con la 119 + la 149 ve también las áreas del bloque y su
 * inventario. El alcance se aplica en InventariosController::listadoConsolidado.
 *
 * No se otorga a ningún perfil: se asigna desde /permisos (Super Admin la tiene por su
 * acceso global). Id explícito: si ya está ocupado por otra cosa, la migración falla en
 * vez de usar otro en silencio.
 */
return new class extends Migration
{
    private const ID = 149;

    private const NOMBRE = 'Mis áreas comunes — Ver áreas del bloque';

    private const ID_MODULO = 6;

    public function up(): void
    {
        $existente = DB::table('cron_opciones')->where('id', self::ID)->value('nombre');

        if ($existente !== null && $existente !== self::NOMBRE) {
            throw new RuntimeException(sprintf(
                'cron_opciones.id %d ya está ocupado por "%s". Ajusta el id aquí, en App\Support\Permiso y en el frontend.',
                self::ID,
                $existente,
            ));
        }

        if ($existente === null) {
            DB::table('cron_opciones')->insert([
                'id' => self::ID,
                'nombre' => self::NOMBRE,
                'id_modulo' => self::ID_MODULO,
                'activo' => 1,
                'fechareg' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('cron_permisos')->where('id_opcion', self::ID)->delete();
        DB::table('cron_opciones')->where('id', self::ID)->where('nombre', self::NOMBRE)->delete();
    }
};
