<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué formato de carta de recomendación debe diligenciar esta institución al enviar la
 * carta (ver InstitucionController::guardarCartaRecomendacion / frontend
 * CartaRecomendacion.page.tsx). "Play and Learn" es el programa de preescolar propio del
 * colegio — usa un formato con campos distintos al de coordinación/psicología genérico
 * (ver PDFs en src/assets/Admissions/LettersOfRecommendation en el frontend).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instituciones', function (Blueprint $table) {
            $table->string('tipo_documento', 30)->default('coordinador_psicologo')->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('instituciones', function (Blueprint $table) {
            $table->dropColumn('tipo_documento');
        });
    }
};
