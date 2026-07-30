<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AsistenciaGestionService::registrarAsistencia() hacía "check-then-insert"
     * (exists() + create()) sin constraint en BD: dos pushes casi simultáneos
     * del mismo usuario (p. ej. un reintento del dispositivo) podían pasar el
     * exists() antes de que el primero insertara, duplicando la asistencia.
     * Esto ya generó ~9,375 filas duplicadas históricas en producción (grupos
     * de mismo id_user+fecha_asistencia con varias filas, algunos hasta con
     * más de 50). Se conserva solo la fila más antigua (menor id, que es la
     * primera marcación real insertada) de cada grupo antes de poder crear el
     * índice único que evita que esto vuelva a pasar.
     */
    public function up(): void
    {
        DB::statement('
            DELETE FROM asistencia_gestion
            WHERE id NOT IN (
                SELECT id FROM (
                    SELECT MIN(id) AS id
                    FROM asistencia_gestion
                    GROUP BY id_user, fecha_asistencia
                ) AS filas_a_conservar
            )
        ');

        Schema::table('asistencia_gestion', function (Blueprint $table) {
            $table->unique(['id_user', 'fecha_asistencia'], 'asistencia_gestion_user_fecha_unique');
        });
    }

    public function down(): void
    {
        Schema::table('asistencia_gestion', function (Blueprint $table) {
            $table->dropUnique('asistencia_gestion_user_fecha_unique');
        });
    }
};
