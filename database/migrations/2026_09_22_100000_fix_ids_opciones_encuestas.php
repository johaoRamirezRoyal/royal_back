<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrige el drift de ids visto de nuevo (mismo patrón que Instituciones 104/106,
 * Noticias 69/107, Evaluaciones 101-103): la migración
 * 2026_09_22_090600_seed_opciones_encuestas insertó "Encuestas — Administrar..."/
 * "Encuestas — Ver resultados" con ids 126/127 en esta BD, pero en producción esas
 * mismas opciones (creadas independientemente, con otras filas ya ocupando el rango
 * 126-128 ahí) terminaron en 129/130. Renumera las filas locales para que coincidan
 * con producción — sin esto, el frontend (hardcodea 129/130) y el backend quedarían
 * mirando ids que no existen en esta BD.
 *
 * cron_opciones/cron_permisos no tienen FK declarada entre sí (tabla legacy), así que
 * un UPDATE directo del id es seguro siempre que 129/130 estén libres (lo están: el
 * máximo id local antes de esta migración es 127).
 */
return new class extends Migration
{
    private const RENUMERACION = [
        126 => 129, // "Encuestas — Administrar encuestas y salones"
        127 => 130, // "Encuestas — Ver resultados"
    ];

    public function up(): void
    {
        foreach (self::RENUMERACION as $idViejo => $idNuevo) {
            if (DB::table('cron_opciones')->where('id', $idNuevo)->exists()) {
                continue; // ya migrado (o el id nuevo lo ocupa otra opción real) — no tocar
            }

            DB::table('cron_permisos')->where('id_opcion', $idViejo)->update(['id_opcion' => $idNuevo]);
            DB::table('cron_opciones')->where('id', $idViejo)->update(['id' => $idNuevo]);
        }
    }

    public function down(): void
    {
        foreach (self::RENUMERACION as $idViejo => $idNuevo) {
            DB::table('cron_permisos')->where('id_opcion', $idNuevo)->update(['id_opcion' => $idViejo]);
            DB::table('cron_opciones')->where('id', $idNuevo)->update(['id' => $idViejo]);
        }
    }
};
