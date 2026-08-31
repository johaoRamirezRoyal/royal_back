<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dominio de correo que identifica automáticamente a una institución como "Play and
 * Learn" (ver Institucion::TIPOS_DOCUMENTO) al registrar/verificar su correo — no
 * reemplaza el selector manual del admin, solo lo pre-asigna. Ver
 * ConfiguracionInstituciones::tipoDocumentoParaCorreo().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_instituciones', function (Blueprint $table) {
            $table->string('dominio_play_and_learn')->nullable()->after('correo_notificacion');
        });

        DB::table('configuracion_instituciones')
            ->where('id', 1)
            ->update(['dominio_play_and_learn' => 'playandlearn.edu.co']);
    }

    public function down(): void
    {
        Schema::table('configuracion_instituciones', function (Blueprint $table) {
            $table->dropColumn('dominio_play_and_learn');
        });
    }
};
