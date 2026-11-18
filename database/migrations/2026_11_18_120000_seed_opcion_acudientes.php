<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Opción 175 «Acudientes — Crear y gestionar» (módulo 14, Gestión Académica): el módulo
 * /gestion-academica/acudientes, que reemplaza «Acudientes» de Admisiones (112) y «Vinculación
 * Estudiante - Padre» (131). Se otorga a los perfiles que hoy tienen alguna de esas dos, para que
 * nadie pierda acceso. La 112 y la 131 quedan en la base sin uso en el menú.
 */
return new class extends Migration
{
    private const ID = 175;
    private const NOMBRE = 'Acudientes — Crear y gestionar';
    private const MODULO = 14; // Gestión Académica
    private const REEMPLAZA = [112, 131];

    public function up(): void
    {
        $existente = DB::table('cron_opciones')->where('id', self::ID)->value('nombre');

        if ($existente !== null && $existente !== self::NOMBRE) {
            throw new RuntimeException('cron_opciones.id ' . self::ID . " ya está ocupado por \"{$existente}\". Ajusta el id aquí y en App\\Support\\Permiso.");
        }

        if ($existente === null) {
            DB::table('cron_opciones')->insert([
                'id' => self::ID, 'nombre' => self::NOMBRE, 'id_modulo' => self::MODULO, 'activo' => 1, 'fechareg' => now(),
            ]);
        }

        $perfiles = DB::table('cron_permisos')->whereIn('id_opcion', self::REEMPLAZA)->where('activo', 1)
            ->distinct()->pluck('id_perfil');

        foreach ($perfiles as $idPerfil) {
            $yaLaTiene = DB::table('cron_permisos')->where('id_opcion', self::ID)->where('id_perfil', $idPerfil)->exists();

            if (! $yaLaTiene) {
                DB::table('cron_permisos')->insert([
                    'id_opcion' => self::ID, 'id_perfil' => $idPerfil, 'activo' => 1, 'fechareg' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('cron_permisos')->where('id_opcion', self::ID)->delete();
        DB::table('cron_opciones')->where('id', self::ID)->delete();
    }
};
