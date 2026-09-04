<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Módulo nuevo para que el staff vea/busque/active-desactive a los acudientes que se
 * auto-registran vía el flujo público de Admisiones (perfil 6, ver
 * AdmissionsController::familyRegister) — hoy no había ninguna pantalla interna para
 * gestionar esas cuentas. Otorgada por defecto a Super Admin y Admisiones, mismo
 * criterio que 2026_08_31_190000_seed_opcion_ver_instituciones_documentos: no hay dato
 * sensible tipo NIT acá, así que no aplica restringirla solo a Super Admin.
 */
return new class extends Migration
{
    private const NOMBRE_OPCION = 'Gestión de Acudientes';

    private const ID_MODULO_ADMISIONES = 1;

    private const PERFILES_CON_ACCESO = [1, 9];

    public function up(): void
    {
        $idOpcion = DB::table('cron_opciones')->insertGetId([
            'nombre' => self::NOMBRE_OPCION,
            'id_modulo' => self::ID_MODULO_ADMISIONES,
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
