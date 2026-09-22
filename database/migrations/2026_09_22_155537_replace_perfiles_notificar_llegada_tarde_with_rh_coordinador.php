<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reemplaza el selector libre de perfiles (`perfiles_notificar_llegada_tarde`) del aviso
 * de llegada tarde de trabajadores por dos toggles fijos: Recursos Humanos (perfil 8, todo
 * el colegio) y el coordinador (perfil 26) del mismo nivel del trabajador — ver
 * AsistenciaGestionService::notificarLlegadaTarde. Simplifica la configuración (ya no hay
 * que elegir perfiles a mano) y resuelve el coordinador correcto por nivel con
 * UsuariosServices::correosPorPerfilesYNivel, el mismo mecanismo que ya usa
 * PermisosLicenciasServices para enrutar avisos al coordinador de nivel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_asistencia', function (Blueprint $table) {
            $table->boolean('notificar_recursos_humanos')->default(false)->after('notificar_llegada_tarde_trabajador');
            $table->boolean('notificar_coordinador_nivel')->default(false)->after('notificar_recursos_humanos');
            $table->dropColumn('perfiles_notificar_llegada_tarde');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_asistencia', function (Blueprint $table) {
            $table->json('perfiles_notificar_llegada_tarde')->nullable()->after('notificar_llegada_tarde_trabajador');
            $table->dropColumn(['notificar_recursos_humanos', 'notificar_coordinador_nivel']);
        });
    }
};
