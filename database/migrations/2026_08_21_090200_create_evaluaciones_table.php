<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluaciones', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->integer('id_servicio');
            $table->integer('id_user');
            $table->tinyInteger('activo')->default(1);
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('id_servicio', 'fk_evaluaciones_servicio')
                ->references('id')->on('evaluaciones_servicios');
            $table->foreign('id_user', 'fk_evaluaciones_user')
                ->references('id_user')->on('usuarios');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluaciones');
    }
};
