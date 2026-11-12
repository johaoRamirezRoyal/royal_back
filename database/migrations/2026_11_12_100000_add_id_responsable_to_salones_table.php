<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Responsable del salón: si tiene uno, sus reservas quedan pendientes de autorización
 * (reservas.confirmado = 3) hasta que él las apruebe o rechace (ver
 * ReservasServices::decidirReservas). Sin FK porque `usuarios` es legacy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salones', function (Blueprint $table) {
            $table->unsignedInteger('id_responsable')->nullable()->after('sonido');
        });
    }

    public function down(): void
    {
        Schema::table('salones', function (Blueprint $table) {
            $table->dropColumn('id_responsable');
        });
    }
};
