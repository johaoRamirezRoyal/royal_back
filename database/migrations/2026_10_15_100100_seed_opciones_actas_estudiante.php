<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Opciones del módulo "Registro de actas" (Académico, 14) — ver ActasEstudianteController:
 *  - 136 Ver actas de estudiantes: listado, conteos y gráficas.
 *  - 137 Gestionar actas de estudiantes: crear, editar y eliminar (también da lectura).
 *
 * Ids explícitos, mismo patrón que la 132 (133-135 son de Observaciones, módulo 15): el frontend y el controller usan el id fijo. Si
 * alguno ya está ocupado por otra opción, la migración falla en vez de usar otro id.
 */
return new class extends Migration
{
    private const OPCIONES = [
        136 => 'Ver actas de estudiantes',
        137 => 'Gestionar actas de estudiantes',
    ];

    private const ID_MODULO_ACADEMICO = 14;

    private const PERFILES_CON_ACCESO = [1];

    public function up(): void
    {
        foreach (self::OPCIONES as $idOpcion => $nombre) {
            $existente = DB::table('cron_opciones')->where('id', $idOpcion)->value('nombre');

            if ($existente !== null && $existente !== $nombre) {
                throw new RuntimeException(sprintf(
                    'cron_opciones.id %d ya está ocupado por "%s". Ajusta el id aquí, en ActasEstudianteController y en el frontend (PermissionGate/sidebar).',
                    $idOpcion,
                    $existente,
                ));
            }

            if ($existente === null) {
                DB::table('cron_opciones')->insert([
                    'id' => $idOpcion,
                    'nombre' => $nombre,
                    'id_modulo' => self::ID_MODULO_ACADEMICO,
                    'activo' => 1,
                    'fechareg' => now(),
                ]);
            }

            foreach (self::PERFILES_CON_ACCESO as $idPerfil) {
                $yaOtorgada = DB::table('cron_permisos')
                    ->where('id_opcion', $idOpcion)
                    ->where('id_perfil', $idPerfil)
                    ->exists();

                if (! $yaOtorgada) {
                    DB::table('cron_permisos')->insert([
                        'id_opcion' => $idOpcion,
                        'id_perfil' => $idPerfil,
                        'activo' => 1,
                        'fechareg' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::OPCIONES as $idOpcion => $nombre) {
            DB::table('cron_permisos')->where('id_opcion', $idOpcion)->delete();
            DB::table('cron_opciones')->where('id', $idOpcion)->where('nombre', $nombre)->delete();
        }
    }
};
