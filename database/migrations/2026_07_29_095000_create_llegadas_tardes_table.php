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
        Schema::create('llegadas_tardes', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary()->index();

            $table->integer('id_alumno');
            $table->foreign('id_alumno')->references('id_user')->on('usuarios')->restrictOnDelete();

            $table->integer('id_periodo_academico');
            $table->foreign('id_periodo_academico')->references('id')->on('periodo_academico')->restrictOnDelete();

            $table->date('fecha');
            $table->time('hora');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('llegadas_tardes');
    }
};
