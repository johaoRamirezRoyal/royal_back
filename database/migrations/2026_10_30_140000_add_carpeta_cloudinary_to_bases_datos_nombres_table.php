<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carpeta raíz de cada colegio en Cloudinary (editable en "Bases de datos"): sus archivos van
 * a `<carpeta>/<ruta>`. NULL = la de fábrica (BasesDatosService::carpetaPorDefecto). `nombre`
 * pasa a aceptar NULL: una fila puede existir solo para guardar la carpeta.
 */
return new class extends Migration
{
    protected $connection = 'admin_management';

    public function up(): void
    {
        Schema::table('bases_datos_nombres', function (Blueprint $table) {
            $table->string('nombre', 190)->nullable()->change();
            $table->string('carpeta_cloudinary', 120)->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('bases_datos_nombres', function (Blueprint $table) {
            $table->dropColumn('carpeta_cloudinary');
        });
    }
};
