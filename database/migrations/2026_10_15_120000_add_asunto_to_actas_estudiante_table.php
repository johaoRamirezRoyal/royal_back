<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Asunto del acta: texto corto que la identifica (en el listado y en "Asunto" del PDF).
     * Obligatorio al crear/editar (ActasEstudianteController); nullable solo para las actas
     * registradas antes de existir.
     */
    public function up(): void
    {
        Schema::table('actas_estudiante', function (Blueprint $table) {
            $table->string('asunto', 200)->nullable()->after('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('actas_estudiante', function (Blueprint $table) {
            $table->dropColumn('asunto');
        });
    }
};
