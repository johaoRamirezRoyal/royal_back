<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluaciones_perfil', function (Blueprint $table) {
            $table->id();
            $table->integer('id_evaluacion');
            $table->integer('id_perfil');

            $table->foreign('id_evaluacion', 'fk_eval_perfil_evaluacion')
                ->references('id')->on('evaluaciones')->cascadeOnDelete();
            $table->foreign('id_perfil', 'fk_eval_perfil_perfil')
                ->references('id_perfil')->on('perfiles');

            $table->unique(['id_evaluacion', 'id_perfil'], 'uq_evaluacion_perfil');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluaciones_perfil');
    }
};
