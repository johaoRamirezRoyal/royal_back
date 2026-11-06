<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Opciones de notas (módulo 14, Gestión Académica). Ver NotasController.
 * - 149 "Consultar notas": ver en solo lectura las notas de cualquier curso.
 * - 150 "Gestionar logros": editar y enviar a revisión los logros de los cursos de su nivel.
 * - 151 "Aprobar logros": además aprobar, devolver y reabrir, en su nivel.
 * Calificar no lleva opción: es autoservicio del docente de la carga (perfiles 3 y 14).
 *
 * No se otorgan a ningún perfil: se asignan desde /permisos. Ids explícitos: si uno ya está
 * ocupado por otra cosa, la migración falla en vez de usar otro en silencio.
 */
return new class extends Migration
{
    private const OPCIONES = [
        149 => 'Gestión Académica — Consultar notas',
        150 => 'Gestión Académica — Gestionar logros',
        151 => 'Gestión Académica — Aprobar logros',
    ];

    private const ID_MODULO = 14;

    public function up(): void
    {
        foreach (self::OPCIONES as $id => $nombre) {
            $existente = DB::table('cron_opciones')->where('id', $id)->value('nombre');

            if ($existente !== null && $existente !== $nombre) {
                throw new RuntimeException(sprintf(
                    'cron_opciones.id %d ya está ocupado por "%s". Ajusta el id aquí, en App\Support\Permiso y en el frontend.',
                    $id,
                    $existente,
                ));
            }

            if ($existente === null) {
                DB::table('cron_opciones')->insert([
                    'id' => $id,
                    'nombre' => $nombre,
                    'id_modulo' => self::ID_MODULO,
                    'activo' => 1,
                    'fechareg' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::OPCIONES as $id => $nombre) {
            DB::table('cron_permisos')->where('id_opcion', $id)->delete();
            DB::table('cron_opciones')->where('id', $id)->where('nombre', $nombre)->delete();
        }
    }
};
