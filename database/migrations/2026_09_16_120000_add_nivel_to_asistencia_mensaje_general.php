<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El mensaje general se enviaba por correo individualmente a cada usuario de TODOS
     * los niveles activos (~2149 destinatarios en el run del 2026-09-16), agotando en
     * minutos el límite de "Max Emails Per Hour" de la cuenta (ver MailRateLimitException).
     * Ahora exige elegir explícitamente un nivel (0 = "Todos" sigue siendo válido, pero
     * se resuelve a UN solo correo real de distribución — ver
     * NoticiasService::ALIAS_DISTRIBUCION_POR_NIVEL/resolverAliasDistribucion — en vez de
     * un envío por usuario). Nullable aquí solo porque la fila legacy existente no tiene
     * valor todavía; NoticiasController::actualizarGeneral exige el campo desde la
     * próxima edición.
     */
    public function up(): void
    {
        Schema::table('asistencia_mensaje_general', function (Blueprint $table) {
            $table->unsignedInteger('nivel')->nullable()->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('asistencia_mensaje_general', function (Blueprint $table) {
            $table->dropColumn('nivel');
        });
    }
};
