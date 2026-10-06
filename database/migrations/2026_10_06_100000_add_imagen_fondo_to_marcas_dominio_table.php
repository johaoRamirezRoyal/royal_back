<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Vive en `admin_management` (ver 2026_08_26_100000_create_marcas_dominio_table). */
    protected $connection = 'admin_management';

    /** Imagen de referencia de la institución (fachada, campus...) — se pinta como fondo
     * del friso del Login, bajo un velo oscuro; sin ella, el friso queda en el color. */
    public function up(): void
    {
        Schema::table('marcas_dominio', function (Blueprint $table) {
            $table->string('imagen_fondo_path', 255)->nullable()->after('logo_public_id');
            $table->string('imagen_fondo_public_id', 255)->nullable()->after('imagen_fondo_path');
        });
    }

    public function down(): void
    {
        Schema::table('marcas_dominio', function (Blueprint $table) {
            $table->dropColumn(['imagen_fondo_path', 'imagen_fondo_public_id']);
        });
    }
};
