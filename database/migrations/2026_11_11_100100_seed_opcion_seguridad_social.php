<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Opción 150 (módulo 3, Gestión Humana): EPS/AFP/ARL de los trabajadores con curso de
 * altura y su catálogo de entidades (/gestion-humana/cursos-altura, ver
 * CursosAlturaController). Se otorga a los perfiles que ya tienen la 143 (Cursos de altura).
 *
 * Id explícito: si ya está ocupado por otra cosa, la migración falla en vez de usar otro en
 * silencio.
 */
return new class extends Migration
{
    private const ID = 150;

    private const NOMBRE = 'Gestión Humana — Seguridad social de trabajadores';

    private const ID_MODULO = 3;

    private const OPCION_CURSOS_ALTURA = 143;

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

        $perfiles = DB::table('cron_permisos')
            ->where('id_opcion', self::OPCION_CURSOS_ALTURA)
            ->where('activo', 1)
            ->whereNotIn('id_perfil', DB::table('cron_permisos')->where('id_opcion', self::ID)->select('id_perfil'))
            ->distinct()
            ->pluck('id_perfil');

        foreach ($perfiles as $idPerfil) {
            DB::table('cron_permisos')->insert([
                'id_opcion' => self::ID,
                'id_perfil' => $idPerfil,
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
