<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IP del último login exitoso — usada por el login por contraseña de acudientes
 * (AdmissionsController::loginConPassword) para exigir el mismo código por correo
 * que ya usa InstitucionController::login() cuando la IP cambia respecto al último
 * acceso. Null hasta el primer login por contraseña (el flujo de solo-OTP existente
 * también la actualiza, ver AdmissionsController::forgetVerificationCode).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('ultima_ip', 45)->nullable()->after('pass');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('ultima_ip');
        });
    }
};
