<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluaciones_nivel', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_evaluacion');
            $table->integer('id_nivel');

            $table->foreign('id_evaluacion', 'fk_eval_nivel_evaluacion')
                ->references('id')->on('evaluaciones')->cascadeOnDelete();
            $table->foreign('id_nivel', 'fk_eval_nivel_nivel')
                ->references('id')->on('nivel');

            $table->unique(['id_evaluacion', 'id_nivel'], 'uq_evaluacion_nivel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluaciones_nivel');
    }
};
