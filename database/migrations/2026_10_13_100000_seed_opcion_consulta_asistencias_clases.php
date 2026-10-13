<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Opción "Consulta de asistencias de clases" (módulo Académico, 14): ver las asistencias
 * de TODAS las clases (filtrar por fecha/curso/docente/asignatura/estado y abrir el
 * registro por estudiante) sin ser el docente que las dicta y sin la opción 99 completa.
 * Solo lectura — ver ConsultaAsistenciaClaseController.
 *
 * Id explícito (132) en vez de insertGetId: el frontend (PermissionGate) y el controller
 * usan el id fijo, y con insertGetId el id real dependía del estado de cada BD — así se
 * rompieron Noticias, Instituciones y Acudientes (ver AGENTS.md "Sistema de permisos").
 * Si el 132 ya está tomado por otra opción, la migración falla con un mensaje claro en
 * vez de crear la opción con otro id en silencio.
 */
return new class extends Migration
{
    private const ID_OPCION = 132;

    private const NOMBRE_OPCION = 'Consulta de asistencias de clases';

    private const ID_MODULO_ACADEMICO = 14;

    private const PERFILES_CON_ACCESO = [1];

    public function up(): void
    {
        $existente = DB::table('cron_opciones')->where('id', self::ID_OPCION)->value('nombre');

        if ($existente !== null && $existente !== self::NOMBRE_OPCION) {
            throw new RuntimeException(sprintf(
                'cron_opciones.id %d ya está ocupado por "%s". Ajusta ID_OPCION aquí, en ConsultaAsistenciaClaseController y en el frontend (PermissionGate/sidebar).',
                self::ID_OPCION,
                $existente,
            ));
        }

        if ($existente === null) {
            DB::table('cron_opciones')->insert([
                'id' => self::ID_OPCION,
                'nombre' => self::NOMBRE_OPCION,
                'id_modulo' => self::ID_MODULO_ACADEMICO,
                'activo' => 1,
                'fechareg' => now(),
            ]);
        }

        foreach (self::PERFILES_CON_ACCESO as $idPerfil) {
            $yaOtorgada = DB::table('cron_permisos')
                ->where('id_opcion', self::ID_OPCION)
                ->where('id_perfil', $idPerfil)
                ->exists();

            if (! $yaOtorgada) {
                DB::table('cron_permisos')->insert([
                    'id_opcion' => self::ID_OPCION,
                    'id_perfil' => $idPerfil,
                    'activo' => 1,
                    'fechareg' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('cron_permisos')->where('id_opcion', self::ID_OPCION)->delete();
        DB::table('cron_opciones')->where('id', self::ID_OPCION)->where('nombre', self::NOMBRE_OPCION)->delete();
    }
};
