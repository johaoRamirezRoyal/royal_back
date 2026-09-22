<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Imagen/GIF opcional del banner informativo. Guardaba la ruta relativa dentro del disco de
 * uploads ("banner/uuid.gif"); desde
 * `2026_09_22_100000_add_imagen_public_id_to_banner_informativo_table` guarda la URL de
 * Cloudinary en su lugar (servida directo desde ahí, el banner también se ve en el login,
 * sin sesión).
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
