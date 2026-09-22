<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encuestas_preguntas', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_encuesta');
            // Reusa el catálogo de tipos de pregunta de Evaluaciones
            // (evaluaciones_tipos_pregunta: seleccion_unica/seleccion_multiple/texto_libre)
            // en vez de duplicarlo — decisión de diseño, ver plan de la funcionalidad.
            // Sin FK real: evaluaciones_tipos_pregunta es MyISAM (legacy) y esta tabla es
            // InnoDB — MySQL no permite una FK InnoDB -> MyISAM (error 150).
            $table->integer('id_tipo_pregunta');
            $table->string('texto');
            $table->tinyInteger('obligatoria')->default(1);
            $table->integer('orden')->default(0);
            $table->timestamps();

            $table->foreign('id_encuesta', 'fk_enc_preguntas_encuesta')
                ->references('id')->on('encuestas')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encuestas_preguntas');
    }
};
