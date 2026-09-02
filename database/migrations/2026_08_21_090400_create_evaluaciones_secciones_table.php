<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluaciones_secciones', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_evaluacion');
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->decimal('porcentaje', 5, 2)->default(100);
            $table->integer('orden')->default(0);
            $table->tinyInteger('activo')->default(1);
            $table->timestamps();

            $table->foreign('id_evaluacion', 'fk_eval_secciones_evaluacion')
                ->references('id')->on('evaluaciones')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluaciones_secciones');
    }
};
