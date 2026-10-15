<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Docente que solicita el acta (quien la diligenció en papel). Nullable: las actas ya
     * registradas no lo tienen. Distinto de id_usuario_registra, que es quien la digita en el
     * sistema. Se imprime en "Nombre del docente" del PDF.
     */
    public function up(): void
    {
        Schema::table('actas_estudiante', function (Blueprint $table) {
            $table->integer('id_docente_solicita')->nullable()->after('observacion');
            $table->foreign('id_docente_solicita')->references('id_user')->on('usuarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('actas_estudiante', function (Blueprint $table) {
            $table->dropForeign(['id_docente_solicita']);
            $table->dropColumn('id_docente_solicita');
        });
    }
};
