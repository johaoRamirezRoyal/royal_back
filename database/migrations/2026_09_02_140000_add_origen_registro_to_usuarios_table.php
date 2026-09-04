<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca explícita de "por dónde entró este registro" — reemplaza la heurística anterior
 * basada en `user_log` (quién lo creó), que resultó no confiable: hay usuarios sin
 * user_log del admin aunque tampoco vinieron de un auto-registro (datos legados,
 * importaciones, etc.), así que "user_log = 1" no distinguía nada de forma consistente.
 * AdmissionsController::familyRegister ahora setea 'admisiones' explícito al crear la
 * cuenta — es el único lugar de la app que escribe esta columna hoy.
 *
 * Sin backfill a propósito (decisión explícita, no un olvido): el histórico existente
 * queda en null — "solo autoregistrados" es una marca hacia adelante, no una
 * reconstrucción retroactiva de cómo entró cada cuenta ya creada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('origen_registro', 30)->nullable()->after('user_log');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('origen_registro');
        });
    }
};
