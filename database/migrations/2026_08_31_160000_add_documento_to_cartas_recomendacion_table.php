<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada carta enviada se renderiza a PDF (ver CartaRecomendacionPdfService) y se sube a
 * Cloudinary (mismo servicio que ya usa Admisiones, App\Services\Cloudinary\CloudinaryService)
 * — se guarda la URL para poder listarla/descargarla después, tanto en el portal de la
 * institución como en el módulo admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cartas_recomendacion', function (Blueprint $table) {
            $table->string('documento_url')->nullable()->after('datos');
            $table->string('documento_public_id')->nullable()->after('documento_url');
        });
    }

    public function down(): void
    {
        Schema::table('cartas_recomendacion', function (Blueprint $table) {
            $table->dropColumn(['documento_url', 'documento_public_id']);
        });
    }
};
