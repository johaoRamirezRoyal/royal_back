<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IP del último login completado (con o sin verificación por correo) — punto de
 * comparación para decidir si un nuevo login desde otra IP debe re-verificarse por
 * correo. Ver InstitucionController::login()/verifyLoginOtp().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instituciones', function (Blueprint $table) {
            $table->string('ultima_ip', 45)->nullable()->after('primer_ingreso_at');
        });
    }

    public function down(): void
    {
        Schema::table('instituciones', function (Blueprint $table) {
            $table->dropColumn('ultima_ip');
        });
    }
};
