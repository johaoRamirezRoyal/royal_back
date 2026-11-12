<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * perfiles.tipo (App\Enums\TipoPerfil): qué clase de persona agrupa cada perfil, para que el
 * código deje de preguntar por números de perfil, que cambian de un colegio a otro.
 *
 * Se llena por NOMBRE, no por id: así queda bien en cualquier colegio (en el Royal el
 * Coordinador es el 26; en el Hebreo, el 22). Los nombres que no reconoce quedan
 * ADMINISTRATIVO y cada colegio los ajusta en /permisos. OPERATIVO replica
 * PermisosLicenciasServices::PERFILES_ASIGNABLES del Royal (10, 23, 27, 32).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perfiles', function (Blueprint $table) {
            $table->string('tipo', 20)->default('ADMINISTRATIVO')->after('nombre');
        });

        DB::statement("
            UPDATE perfiles SET tipo = CASE LOWER(TRIM(REPLACE(REPLACE(nombre, '\\r', ''), '\\n', ' ')))
                WHEN 'docente' THEN 'DOCENTE'
                WHEN 'docente ict' THEN 'DOCENTE'
                WHEN 'trabajador' THEN 'OPERATIVO'
                WHEN 'operativo' THEN 'OPERATIVO'
                WHEN 'supervisor operativo' THEN 'OPERATIVO'
                WHEN 'mensajero' THEN 'OPERATIVO'
                WHEN 'estudiante' THEN 'ESTUDIANTE'
                WHEN 'acudiente' THEN 'ACUDIENTE'
                WHEN 'proveedor' THEN 'PROVEEDOR'
                WHEN 'egresado' THEN 'EGRESADO'
                ELSE 'ADMINISTRATIVO'
            END
        ");
    }

    public function down(): void
    {
        Schema::table('perfiles', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
