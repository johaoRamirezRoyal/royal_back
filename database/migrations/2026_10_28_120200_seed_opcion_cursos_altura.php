<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Opción 143 (módulo 3, Gestión Humana): CRUD de cursos de trabajo en alturas
 * (/gestion-humana/cursos-altura, ver CursosAlturaController).
 *
 * No se otorga a ningún perfil: se asigna desde /permisos. Id explícito: si ya está
 * ocupado por otra cosa, la migración falla en vez de usar otro en silencio.
 */
return new class extends Migration
{
    private const ID = 143;

    private const NOMBRE = 'Gestión Humana — Cursos de altura';

    private const ID_MODULO = 3;

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
