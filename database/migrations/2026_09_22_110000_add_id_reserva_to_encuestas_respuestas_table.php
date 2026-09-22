<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encuestas_respuestas', function (Blueprint $table) {
            // Reserva del salón, del mismo día, que el visitante indica antes de responder
            // — nullable porque respuestas creadas antes de este cambio no la tienen.
            // FK real: `reservas` es InnoDB (confirmado), igual que esta tabla.
            $table->integer('id_reserva')->nullable()->after('id_salon');

            $table->foreign('id_reserva', 'fk_enc_respuestas_reserva')
                ->references('id')->on('reservas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('encuestas_respuestas', function (Blueprint $table) {
            $table->dropForeign('fk_enc_respuestas_reserva');
            $table->dropColumn('id_reserva');
        });
    }
};
