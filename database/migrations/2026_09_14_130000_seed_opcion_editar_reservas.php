<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permiso para editar una reserva ya creada (fecha, hora, salón, descripción, portátiles
 * y sonido) — distinto de la opción 41 (crear/cancelar la reserva propia, autoservicio
 * abierto a casi todos los perfiles) y de la 42 (ver la programación de todos). Editar es
 * una acción administrativa/correctiva sobre reservas de cualquier usuario, por eso arranca
 * con un grant acotado (Super Admin/Administrador) — ver ReservaController::actualizarReserva.
 */
return new class extends Migration
{
    private const ID_MODULO_RESERVAS = 7;
    private const NOMBRE = 'Reservas — Editar reserva';
    private const PERFILES = [1, 2]; // Super Admin, Administrador

    public function up(): void
    {
        $idOpcion = DB::table('cron_opciones')->insertGetId([
            'nombre' => self::NOMBRE,
            'id_modulo' => self::ID_MODULO_RESERVAS,
            'activo' => 1,
            'fechareg' => now(),
        ]);

        foreach (self::PERFILES as $idPerfil) {
            DB::table('cron_permisos')->insert([
                'id_opcion' => $idOpcion,
                'id_perfil' => $idPerfil,
                'activo' => 1,
                'fechareg' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $idOpcion = DB::table('cron_opciones')->where('nombre', self::NOMBRE)->value('id');

        if ($idOpcion) {
            DB::table('cron_permisos')->where('id_opcion', $idOpcion)->delete();
            DB::table('cron_opciones')->where('id', $idOpcion)->delete();
        }
    }
};
