<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cafetería, etapa 1: horarios de atención de cada cafetería y de cada punto de venta. El día
 * es el catálogo `dias_semana` (1 = lunes … 7 = domingo), el mismo de los horarios
 * académicos. Un día puede tener varias franjas (mañana y tarde); el service impide que se
 * crucen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caf_horarios_cafeteria', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('id_cafeteria')->constrained('caf_cafeterias')->cascadeOnDelete();
            $table->integer('id_dia_semana');
            $table->time('hora_apertura');
            $table->time('hora_cierre');
            $table->boolean('activo')->default(true);

            $table->foreign('id_dia_semana')->references('id')->on('dias_semana');
            $table->index(['id_cafeteria', 'id_dia_semana']);
        });

        Schema::create('caf_horarios_punto_venta', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('id_punto_venta')->constrained('caf_puntos_venta')->cascadeOnDelete();
            $table->integer('id_dia_semana');
            $table->time('hora_apertura');
            $table->time('hora_cierre');
            $table->boolean('activo')->default(true);

            $table->foreign('id_dia_semana')->references('id')->on('dias_semana');
            $table->index(['id_punto_venta', 'id_dia_semana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caf_horarios_punto_venta');
        Schema::dropIfExists('caf_horarios_cafeteria');
    }
};
