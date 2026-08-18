<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * true cuando la última notificación por correo de esta llegada tarde (al estudiante
     * y/o a sus acudientes) se envió correctamente — ver
     * LlegadasTarde::enviarNotificacionLlegadaTarde. Se recalcula cada vez que se
     * reenvía manualmente el correo (LlegadasTardeController::reenviarCorreo), a
     * diferencia de `limite_alcanzado` que queda fijo en el momento del registro.
     */
    public function up(): void
    {
        Schema::table('llegadas_tardes', function (Blueprint $table) {
            $table->boolean('enviado')->default(false)->after('limite_alcanzado');
        });
    }

    public function down(): void
    {
        Schema::table('llegadas_tardes', function (Blueprint $table) {
            $table->dropColumn('enviado');
        });
    }
};
