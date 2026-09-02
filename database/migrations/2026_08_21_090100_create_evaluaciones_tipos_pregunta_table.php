<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo legacy sin CRUD (ver EvaluacionesController::listarTiposPregunta, solo
     * lectura) — los slugs se sembran a mano aquí porque son los únicos que consume el
     * frontend (PreguntaField.parts.tsx): "seleccion_unica"/"seleccion_multiple" usan
     * `opciones` con puntaje por opción, "texto_libre" es respuesta abierta sin puntaje.
     */
    public function up(): void
    {
        Schema::create('evaluaciones_tipos_pregunta', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->string('nombre');
            $table->string('slug')->unique();
        });

        DB::table('evaluaciones_tipos_pregunta')->insert([
            ['nombre' => 'Selección única', 'slug' => 'seleccion_unica'],
            ['nombre' => 'Selección múltiple', 'slug' => 'seleccion_multiple'],
            ['nombre' => 'Texto libre', 'slug' => 'texto_libre'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluaciones_tipos_pregunta');
    }
};
