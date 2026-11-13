<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Opciones de notas (módulo 14, Gestión Académica). Ver NotasController.
 * - 152 "Consultar notas": ver en solo lectura las notas de cualquier curso.
 * - 153 "Gestionar referentes": editar y enviar a revisión los referentes de los grados de su nivel.
 * - 154 "Aprobar referentes": además aprobar, devolver y reabrir, en su nivel, y ajustar el
 *   desempeño final de un referente.
 * Calificar no lleva opción: es autoservicio del docente de la carga (perfiles 3 y 14).
 *
 * No se otorgan a ningún perfil: se asignan desde /permisos. Ids explícitos: si uno ya está
 * ocupado por otra cosa, la migración falla en vez de usar otro en silencio.
 */
return new class extends Migration
{
    private const OPCIONES = [
        152 => 'Gestión Académica — Consultar notas',
        153 => 'Gestión Académica — Gestionar referentes',
        154 => 'Gestión Académica — Aprobar referentes',
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
