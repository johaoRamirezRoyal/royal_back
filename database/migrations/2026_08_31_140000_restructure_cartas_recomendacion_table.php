<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La carta pasa de ser un solo texto libre a los campos reales del formato oficial
 * "Carta de recomendación Coordinación/Psicología" (PDF en
 * src/assets/Admissions/LettersOfRecommendation en el frontend) — se guardan en `datos`
 * (JSON) en vez de columnas sueltas por lo extenso del formulario (preguntas SI/NO +
 * comentarios repetidas). `idioma` registra en cuál idioma se diligenció (es/en), el PDF
 * tiene ambas versiones. No hay filas reales en producción todavía, por eso se reestructura
 * en vez de migrar datos de `contenido`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cartas_recomendacion', function (Blueprint $table) {
            $table->dropColumn('contenido');
            $table->string('idioma', 2)->default('es')->after('id_institucion');
            $table->json('datos')->after('idioma');
        });
    }

    public function down(): void
    {
        Schema::table('cartas_recomendacion', function (Blueprint $table) {
            $table->dropColumn(['idioma', 'datos']);
            $table->text('contenido')->after('id_institucion');
        });
    }
};
