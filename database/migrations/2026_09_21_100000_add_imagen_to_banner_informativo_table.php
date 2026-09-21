<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Imagen/GIF opcional del banner informativo — guarda la ruta relativa dentro del disco de
 * uploads ("banner/uuid.gif"), servida públicamente por
 * BannerInformativoController::verImagen (el banner también se ve en el login, sin sesión).
 */
return new class extends Migration
{
    /** Tabla transversal en `admin_management` — ver create_banner_informativo_table. */
    protected $connection = 'admin_management';

    public function up(): void
    {
        Schema::table('banner_informativo', function (Blueprint $table) {
            $table->string('imagen', 255)->nullable()->after('mensaje');
        });
    }

    public function down(): void
    {
        Schema::table('banner_informativo', function (Blueprint $table) {
            $table->dropColumn('imagen');
        });
    }
};
