<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * promocion_sugerencias: estudiantes que el director de grupo sugiere que repitan el año que
 * se cierra (página "Mi curso"). Solo sugerencia: la promoción (opción 145) los trae ya
 * retirados y coordinación decide. Un estudiante, una fila por año. Sin FK: curso y
 * anio_escolar no tienen el mismo motor en todas las BD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promocion_sugerencias', function (Blueprint $table) {
            $table->id();
            $table->integer('id_anio_escolar');
            $table->integer('id_curso')->index();
            $table->integer('id_estudiante');
            $table->integer('id_director');
            $table->dateTime('fecha');
            $table->unique(['id_anio_escolar', 'id_estudiante']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promocion_sugerencias');
    }
};
