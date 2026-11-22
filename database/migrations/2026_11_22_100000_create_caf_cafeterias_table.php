<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cafetería, etapa 1: cafeterías de la institución. La base del colegio ya separa una
 * institución de otra, así que no lleva id de institución. Sin `id_sede`: OMNIA no tiene tabla
 * de sedes (decisión C2 del modelo). Los usuarios van como `integer` sin FK, como el resto de
 * tablas que referencian `usuarios` (legacy). Estados: App\Enums\Cafeteria\EstadoCafeteria.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caf_cafeterias', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 120);
            $table->text('descripcion')->nullable();
            $table->string('estado', 20)->default('BORRADOR')->index();
            $table->integer('creado_por')->index();
            $table->integer('actualizado_por')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caf_cafeterias');
    }
};
