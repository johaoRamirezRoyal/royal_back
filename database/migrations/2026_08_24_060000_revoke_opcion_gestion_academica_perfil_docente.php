<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ID_OPCION_GESTION_ACADEMICA = 99;
    private const ID_PERFIL_DOCENTE = 3;

    /**
     * El perfil Docente tenía otorgada la opción 99 (Gestión Académica) en
     * cron_permisos. Eso no solo mostraba "Configuración académica" y "Llegadas tarde"
     * en el menú del docente (que el propio GestionAcademicaController deja explícito
     * que NO le corresponden — ver METODOS_DOCENTE), sino que le daba acceso completo
     * por API a todo el controller ($tieneAccesoCompleto en el constructor), saltándose
     * la restricción a solo sus métodos de autoservicio. Se desactiva (no se borra) para
     * poder revertir con down() si algún entorno la necesitaba por otra razón.
     */
    public function up(): void
    {
        DB::table('cron_permisos')
            ->where('id_opcion', self::ID_OPCION_GESTION_ACADEMICA)
            ->where('id_perfil', self::ID_PERFIL_DOCENTE)
            ->update(['activo' => 0]);
    }

    public function down(): void
    {
        DB::table('cron_permisos')
            ->where('id_opcion', self::ID_OPCION_GESTION_ACADEMICA)
            ->where('id_perfil', self::ID_PERFIL_DOCENTE)
            ->update(['activo' => 1]);
    }
};
