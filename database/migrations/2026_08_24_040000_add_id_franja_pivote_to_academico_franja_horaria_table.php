<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rastrea de qué franja "pivote" viene una marcación no asignable replicada vía
     * "aplicar a todos los días" (ver FranjaHorariaService::actualizarHorarioFranja).
     * null = franja independiente (pivote propio, o marcada no asignable a mano sin pasar
     * por el replicado); no-null = depende de esa franja pivote y no puede volver a
     * recibir una marcación global de OTRO pivote mientras siga dependiendo de esta — hay
     * que eliminarla (nullOnDelete libera a sus dependientes) y agregar la deseada aparte.
     */
    public function up(): void
    {
        Schema::table('academico_franja_horaria', function (Blueprint $table) {
            $table->integer('id_franja_pivote')->nullable()->after('etiqueta');
            $table->foreign('id_franja_pivote')->references('id')->on('academico_franja_horaria')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('academico_franja_horaria', function (Blueprint $table) {
            $table->dropForeign(['id_franja_pivote']);
            $table->dropColumn('id_franja_pivote');
        });
    }
};
