<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `repetir_anual`: solo los cumpleaños marcados se repiten cada año (correo y Home, por mes
 * y día — ver NoticiasService::enviarProgramadosDelDia/aniversarioVisible). Default false a
 * propósito: en producción hay ~290 cumpleaños previos (2022–2026, una fila nueva por
 * persona cada año, nunca enviados); si se repitieran todos, la misma persona recibiría
 * varias felicitaciones el mismo día. Esos siguen con su fecha única y se convierten a mano
 * (formulario o acción masiva "Repetir cada año").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asistencia_mensaje', function (Blueprint $table) {
            $table->boolean('repetir_anual')->default(false)->after('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('asistencia_mensaje', function (Blueprint $table) {
            $table->dropColumn('repetir_anual');
        });
    }
};
