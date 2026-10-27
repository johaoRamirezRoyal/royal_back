<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resultado del envío por correo de una noticia programada. Antes `enviado_at` se marcaba
 * aunque el correo no llegara a nadie (MailService::send devuelve false), así que un fallo
 * no se distinguía de un envío correcto. `envio_fallido` habilita "Reenviar correo" en
 * Cumpleaños; `envio_detalle` dice qué pasó ("Se envió a 0 de 1 destinatarios"). Las filas
 * anteriores quedan en false: no hay forma de saber si fallaron.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asistencia_mensaje', function (Blueprint $table) {
            $table->boolean('envio_fallido')->default(false)->after('enviado_at');
            $table->string('envio_detalle', 255)->nullable()->after('envio_fallido');
        });
    }

    public function down(): void
    {
        Schema::table('asistencia_mensaje', function (Blueprint $table) {
            $table->dropColumn(['envio_fallido', 'envio_detalle']);
        });
    }
};
