<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retira la repetición anual de los cumpleaños registrados a mano: las felicitaciones las
 * genera ahora la Configuración de cumpleaños (CumpleanosService), una por persona y año.
 * Dejarlos repitiéndose haría que una persona recibiera dos felicitaciones el mismo día.
 * La columna `repetir_anual` se conserva (siempre en 0) para no tocar la estructura de la
 * tabla legacy (MyISAM).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('asistencia_mensaje')->where('repetir_anual', 1)->update(['repetir_anual' => 0]);
    }

    public function down(): void
    {
        // Irreversible a propósito: no se guarda cuáles se repetían.
    }
};
