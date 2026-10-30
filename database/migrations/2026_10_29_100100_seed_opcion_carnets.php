<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Opción 144 "Carnets institucionales" (módulo 10, Carnet): imprimir carnets, administrar
 * sus fotos y llevar el control de entregas (ver CarnetController). El carnet digital de
 * cada usuario (/carnets/mio) no la necesita.
 *
 * No se otorga a ningún perfil: se asigna desde /permisos (Super Admin la tiene por su acceso
 * global). Id explícito: si ya está ocupado por otra cosa, la migración falla en vez de usar
 * otro en silencio.
 */
return new class extends Migration
{
    private const ID_OPCION = 144;

    private const NOMBRE = 'Carnets institucionales';

    private const ID_MODULO = 10;

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
