<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alcance del banner informativo: `dominio` NULL = global (todos los colegios/dominios),
 * con valor = solo visible para visitantes/usuarios cuyo correo resuelva a ese dominio
 * (mismo dato que `marcas_dominio.dominio`, ver MarcaDominioService::dominioDeCorreo).
 */
return new class extends Migration
{
    /** Tabla transversal en `admin_management` — ver create_banner_informativo_table. */
    protected $connection = 'admin_management';

    public function up(): void
    {
        Schema::table('banner_informativo', function (Blueprint $table) {
            $table->string('dominio', 190)->nullable()->after('mensaje');
        });
    }

    public function down(): void
    {
        Schema::table('banner_informativo', function (Blueprint $table) {
            $table->dropColumn('dominio');
        });
    }
};
