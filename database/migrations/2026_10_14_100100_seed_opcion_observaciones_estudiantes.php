<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Módulo "Gestión Convivencial" (15) y sus opciones de observaciones de estudiantes (ver
 * ObservacionEstudianteController):
 * - 133 "Observaciones de estudiantes": registrar observaciones a CUALQUIER estudiante. Los
 *   docentes no la necesitan — registran por autoservicio a los de sus cursos.
 * - 134 "Ver todas las observaciones": ver las de todos los profesores en todos los cursos
 *   (sin ella, cada quien ve solo las que registró). Sola, es de solo lectura.
 * Ambas otorgadas a Super Admin, Psicólogas (Preescolar/Primaria/Secundaria) y Coordinador.
 *
 * Ids explícitos, mismo motivo que 2026_10_13_100000_seed_opcion_consulta_asistencias_clases:
 * si un id ya está ocupado por otra cosa, la migración falla en vez de usar otro en silencio.
 */
return new class extends Migration
{
    private const ID_MODULO = 15;

    private const NOMBRE_MODULO = 'Gestión Convivencial';

    private const OPCIONES = [
        133 => 'Observaciones de estudiantes',
        134 => 'Ver todas las observaciones',
    ];

    private const PERFILES_CON_ACCESO = [1, 21, 24, 25, 26];

    /** true si el id está libre; excepción si lo ocupa otra cosa. */
    private function libre(string $tabla, int $id, string $nombre): bool
    {
        $existente = DB::table($tabla)->where('id', $id)->value('nombre');

        if ($existente !== null && $existente !== $nombre) {
            throw new RuntimeException(sprintf(
                '%s.id %d ya está ocupado por "%s". Ajusta el id aquí, en ObservacionEstudianteController y en el frontend (PermissionGate/sidebar).',
                $tabla,
                $id,
                $existente,
            ));
        }

        return $existente === null;
    }

    public function up(): void
    {
        if ($this->libre('cron_modulos', self::ID_MODULO, self::NOMBRE_MODULO)) {
            DB::table('cron_modulos')->insert([
                'id' => self::ID_MODULO,
                'nombre' => self::NOMBRE_MODULO,
                'activo' => 1,
                'fechareg' => now(),
            ]);
        }

        foreach (self::OPCIONES as $idOpcion => $nombre) {
            if ($this->libre('cron_opciones', $idOpcion, $nombre)) {
                DB::table('cron_opciones')->insert([
                    'id' => $idOpcion,
                    'nombre' => $nombre,
                    'id_modulo' => self::ID_MODULO,
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

        DB::table('cron_modulos')->where('id', self::ID_MODULO)->where('nombre', self::NOMBRE_MODULO)->delete();
    }
};
