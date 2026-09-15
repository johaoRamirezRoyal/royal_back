<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'admin_management';

    /**
     * Tipo de calendario académico del colegio ('A' = 1 feb–30 nov, mismo año; 'B' = 1
     * ago–30 jun del año siguiente — mismos códigos y rangos que
     * ConfiguracionAcademica::tipo_calendario/AnioEscolarServices en la base operativa de
     * cada colegio) — acá solo se usa para calcular el año escolar mostrado en las
     * pantallas públicas de admisiones (ver AdmissionsTenantProvider en el frontend), NO
     * para abrir/cerrar el año escolar real: eso lo sigue haciendo, de forma independiente,
     * el comando programado `anio-escolar:cerrar-abrir` contra la base propia de cada
     * colegio. Nullable/default 'B': mismo default que trae ConfiguracionAcademica.
     */
    public function up(): void
    {
        Schema::table('colegios_admision', function (Blueprint $table) {
            $table->enum('tipo_calendario', ['A', 'B'])->default('B')->after('connection');
        });
    }

    public function down(): void
    {
        Schema::table('colegios_admision', function (Blueprint $table) {
            $table->dropColumn('tipo_calendario');
        });
    }
};
