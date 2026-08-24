<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las franjas horarias pasan a pertenecer a un esquema (nivel × año escolar) en vez
     * de directamente a un año escolar — ver 2026_08_21_130000_create_academico_esquema_horario_table.
     * `id_anio_escolar` se deja intacta (no se toca ni se borra) para no alterar de forma
     * destructiva una tabla sin migración propia en este repo; el código nuevo simplemente
     * deja de depender de ella.
     */
    public function up(): void
    {
        Schema::table('academico_franja_horaria', function (Blueprint $table) {
            $table->integer('id_esquema')->nullable()->after('id_anio_escolar');
            $table->foreign('id_esquema')->references('id')->on('academico_esquema_horario')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('academico_franja_horaria', function (Blueprint $table) {
            $table->dropForeign(['id_esquema']);
            $table->dropColumn('id_esquema');
        });
    }
};
