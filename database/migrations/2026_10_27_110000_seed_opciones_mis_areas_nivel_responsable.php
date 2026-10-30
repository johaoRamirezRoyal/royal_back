<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Opciones 141 y 142 (módulo 6, Zonas) de "Mis áreas" (/inventario/mis-areas, ver
 * AreasComunesController::misAreas): ver y reportar las áreas comunes
 *   - 141: de los bloques del nivel del usuario.
 *   - 142: de las áreas/salones de las que el usuario es responsable (inventario.id_user).
 *
 * No se otorgan a ningún perfil: se asignan desde /permisos (Super Admin las tiene por su
 * acceso global). Ids explícitos: si alguno ya está ocupado por otra cosa, la migración
 * falla en vez de usar otro en silencio.
 */
return new class extends Migration
{
    private const OPCIONES = [
        141 => 'Mis áreas — Áreas comunes de mi nivel',
        142 => 'Mis áreas — Áreas comunes de mis áreas a cargo',
    ];

    private const ID_MODULO = 6;

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
