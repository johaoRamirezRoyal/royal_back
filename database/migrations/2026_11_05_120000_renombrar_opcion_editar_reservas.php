<?php

use App\Support\Permiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La opción 120 pasa a cubrir editar, cancelar y finalizar cualquier reserva (antes
 * cancelar/finalizar colgaba de la 41 "Reserva de salones", que tienen casi todos los
 * perfiles). Solo se renombra: los perfiles que deban gestionar reservas ajenas se
 * asignan desde /permisos — ver ReservaController::actualizarReserva.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('cron_opciones')->where('id', Permiso::RESERVAS_EDITAR)
            ->update(['nombre' => 'Reservas — Editar y cancelar reservas']);
    }

    public function down(): void
    {
        DB::table('cron_opciones')->where('id', Permiso::RESERVAS_EDITAR)
            ->update(['nombre' => 'Editar Reservas']);
    }
};
