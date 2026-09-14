<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permiso para la vista de autoservicio "Mis áreas" (`/inventario/areas-comunes/mis-areas`):
 * un asistente de nivel/coordinador ve el inventario de los bloques donde `bloque_usuario`
 * lo tiene como responsable — ver BloquesServices::obtenerBloquesResponsable. Distinto de
 * 108/109 (ver 2026_09_10_150000_seed_opciones_areas_comunes.php): esos dos ya dan acceso a
 * TODO el módulo de Áreas Comunes; este solo habilita la pestaña de autoservicio para los
 * mismos perfiles que pueden ser asignados como responsables
 * (BloquesServices::PERFILES_RESPONSABLES).
 */
return new class extends Migration
{
    private const ID_MODULO_ZONAS = 6;
    private const NOMBRE = 'Mis áreas comunes';
    private const PERFILES = [11, 26]; // Asistente de nivel, Coordinador

    public function up(): void
    {
        $idOpcion = DB::table('cron_opciones')->insertGetId([
            'nombre' => self::NOMBRE,
            'id_modulo' => self::ID_MODULO_ZONAS,
            'activo' => 1,
            'fechareg' => now(),
        ]);

        foreach (self::PERFILES as $idPerfil) {
            DB::table('cron_permisos')->insert([
                'id_opcion' => $idOpcion,
                'id_perfil' => $idPerfil,
                'activo' => 1,
                'fechareg' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $idOpcion = DB::table('cron_opciones')->where('nombre', self::NOMBRE)->value('id');

        if ($idOpcion) {
            DB::table('cron_permisos')->where('id_opcion', $idOpcion)->delete();
            DB::table('cron_opciones')->where('id', $idOpcion)->delete();
        }
    }
};
