<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plantillas de carnet (Carnets institucionales → Plantillas), como las de cumpleaños: una
 * imagen de fondo por tipo (estudiante | trabajador) y dónde va cada dato (`elementos`,
 * fracciones del ancho/alto de la imagen, ver CarnetPlantillaService). La plantilla activa de
 * cada tipo es la que se imprime; solo puede haber una activa por tipo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carnet_plantillas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->text('descripcion')->nullable();
            $table->string('tipo', 12); // estudiante | trabajador
            $table->boolean('activo')->default(false);
            $table->string('imagen', 250)->nullable();
            $table->unsignedInteger('ancho')->nullable();
            $table->unsignedInteger('alto')->nullable();
            $table->boolean('transparente')->default(false);
            $table->json('elementos')->nullable();
            $table->integer('id_log')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carnet_plantillas');
    }
};
