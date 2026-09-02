<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluaciones_preguntas', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_seccion');
            $table->integer('id_tipo_pregunta');
            $table->string('texto');
            $table->tinyInteger('obligatoria')->default(1);
            $table->tinyInteger('permite_comentario')->default(0);
            $table->integer('orden')->default(0);
            $table->timestamps();

            $table->foreign('id_seccion', 'fk_eval_preguntas_seccion')
                ->references('id')->on('evaluaciones_secciones')->cascadeOnDelete();
            $table->foreign('id_tipo_pregunta', 'fk_eval_preguntas_tipo')
                ->references('id')->on('evaluaciones_tipos_pregunta');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluaciones_preguntas');
    }
};
