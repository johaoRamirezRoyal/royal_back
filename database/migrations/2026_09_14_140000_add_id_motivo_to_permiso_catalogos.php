<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vincula cada permiso específico de los catálogos "hijos" (`permiso_ley`,
 * `permiso_personal`, `permisos_institucionales`) con su motivo general
 * (`permiso_motivo`) — hoy esa relación no existe en el modelo de datos, solo
 * como convención de 3 IDs de motivo hardcodeados en PHP/TS (1=Personal,
 * 3=Ley, 6=Institucional, ver PermisoMotivo.php) que disparan mostrar el
 * catálogo hijo correspondiente en el formulario, sin guardar cuál ítem
 * puntual quedó asociado a cuál motivo.
 *
 * Sin FK real a nivel de motor: `permiso_ley`/`permiso_personal`/
 * `permisos_institucionales` son MyISAM (heredadas) y `permiso_motivo` es
 * InnoDB — MySQL no soporta FOREIGN KEY entre/con tablas MyISAM. La relación
 * queda a nivel de aplicación (Eloquent `belongsTo` + validación
 * `exists:permiso_motivo,id`), mismo criterio ya usado en el resto del repo
 * para tablas legacy sin FK real (ver AGENTS.md).
 */
return new class extends Migration
{
    private const TABLAS = ['permiso_ley', 'permiso_personal', 'permisos_institucionales'];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->integer('id_motivo')->nullable()->after('nombre_permiso');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn('id_motivo');
            });
        }
    }
};
