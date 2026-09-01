<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluaciones_respuestas_evaluacion', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_evaluacion');
            $table->integer('id_user')->nullable();
            $table->integer('id_nivel')->nullable();
            $table->tinyInteger('anonima')->default(0);
            $table->dateTime('completada_en')->nullable();
            $table->timestamps();

            $table->foreign('id_evaluacion', 'fk_resp_eval_evaluacion')
                ->references('id')->on('evaluaciones')->cascadeOnDelete();
            $table->foreign('id_user', 'fk_resp_eval_user')
                ->references('id_user')->on('usuarios');
            $table->foreign('id_nivel', 'fk_resp_eval_nivel')
                ->references('id')->on('nivel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluaciones_respuestas_evaluacion');
    }
};
