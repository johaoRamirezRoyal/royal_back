<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `imagen` pasa a guardar la URL de Cloudinary (antes ruta relativa en disco local, ver
 * add_imagen_to_banner_informativo_table) — la subida se movió a CloudinaryService porque
 * el disco local del VPS tiene límites de tamaño (upload_max_filesize/post_max_size) más
 * bajos que el max:8192 (8MB) que ya validaba Laravel, y GIFs grandes fallaban con "The
 * imagen failed to upload." antes de llegar al controller. `imagen_public_id` es el id
 * necesario para poder borrar la imagen anterior en Cloudinary al reemplazarla (mismo
 * patrón que `soporte_public_id`/`logo_public_id` en LlegadasTarde/MarcaDominio).
 */
return new class extends Migration
{
    /** Tabla transversal en `admin_management` — ver create_banner_informativo_table. */
    protected $connection = 'admin_management';

    public function up(): void
    {
        Schema::table('banner_informativo', function (Blueprint $table) {
            $table->string('imagen_public_id', 255)->nullable()->after('imagen');
        });
    }

    public function down(): void
    {
        Schema::table('banner_informativo', function (Blueprint $table) {
            $table->dropColumn('imagen_public_id');
        });
    }
};
