<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Áreas académicas (ej. "Ciencias Naturales", "Humanidades") que agrupan asignaturas
     * — ver 2026_08_19_120100_add_id_area_to_academico_asignatura_table.
     */
    public function up(): void
    {
        Schema::create('academico_area', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 255);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academico_area');
    }
};
