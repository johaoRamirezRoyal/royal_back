<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Opciones del Registro de boletines (módulo 14, Gestión Académica). Ver NotasController.
 * - 172: ver el registro y descargar los boletines de los cursos de su nivel (usuarios.id_nivel /
 *   id_nivel_2), sin gestionar ni aprobar referentes.
 * - 173: lo mismo en todos los niveles.
 * (99, 152, 153 y 154 ya lo ven: 99/152 todos los niveles, 153/154 el suyo.)
 *
 * No se otorgan a ningún perfil: se asignan desde /permisos. Ids explícitos: si uno ya está
 * ocupado por otra cosa, la migración falla en vez de usar otro en silencio.
 */
return new class extends Migration
{
    private const OPCIONES = [
        172 => 'Gestión Académica — Ver registro de boletines (su nivel)',
        173 => 'Gestión Académica — Ver registro de boletines (todos los niveles)',
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
