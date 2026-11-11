<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Número de WhatsApp al que va el aviso de cada llegada tarde (reemplaza a los celulares de
 * los acudientes). Null = no se envía WhatsApp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_llegadas_tarde', function (Blueprint $table) {
            $table->string('whatsapp_destino', 20)->nullable()->after('cantidad_limite');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_llegadas_tarde', function (Blueprint $table) {
            $table->dropColumn('whatsapp_destino');
        });
    }
};
