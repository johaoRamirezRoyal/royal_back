<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de tiempo del primer login exitoso por NIT — es el punto de partida del plazo de
 * gracia para registrar el correo (7 días, ver InstitucionController::estaBloqueada) antes
 * de bloquear el envío de la carta de recomendación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instituciones', function (Blueprint $table) {
            $table->timestamp('primer_ingreso_at')->nullable()->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('instituciones', function (Blueprint $table) {
            $table->dropColumn('primer_ingreso_at');
        });
    }
};
