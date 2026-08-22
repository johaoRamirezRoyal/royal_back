<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('periodo_academico', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary()->index();

            $table->integer('id_anio_escolar');
            $table->foreign('id_anio_escolar')->references('id')->on('anio_escolar')->cascadeOnDelete();

            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->boolean('activo')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('periodo_academico');
    }
};
