<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha/hora del último login exitoso — compañera de `ultima_ip` (misma sesión,
 * add_ultima_ip_to_usuarios_table), que solo guarda la IP, no cuándo. Se actualiza en
 * los mismos puntos donde ya se actualiza `ultima_ip` (ver AdmissionsController) — para
 * el módulo de gestión de acudientes (columna "Última conexión").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->timestamp('ultima_conexion')->nullable()->after('ultima_ip');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('ultima_conexion');
        });
    }
};
