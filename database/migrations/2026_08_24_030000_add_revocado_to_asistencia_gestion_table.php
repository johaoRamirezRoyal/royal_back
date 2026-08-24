<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revocar una llegada tarde de un trabajador: el registro de asistencia se conserva
     * (no se borra), pero deja de contar en topUsuariosLlegadasTarde — mismo patrón que
     * `llegadas_tardes.revocado` para estudiantes (App\Services\LlegadasTardeEstudiantes\LlegadasTarde::revocarLlegadaTarde).
     */
    public function up(): void
    {
        Schema::table('asistencia_gestion', function (Blueprint $table) {
            $table->boolean('revocado')->default(false)->after('observacion');
        });
    }

    public function down(): void
    {
        Schema::table('asistencia_gestion', function (Blueprint $table) {
            $table->dropColumn('revocado');
        });
    }
};
