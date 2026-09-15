<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `asistencia_mensaje`/`asistencia_mensaje_general` son tablas legacy (MyISAM, sin
     * migración de creación en este repo) que hasta ahora solo alimentaban una pantalla
     * de administración de contenido (NoticiasController) — nada las enviaba por correo.
     * Estas columnas son el seguimiento que necesita el nuevo comando programado
     * `noticias:enviar-diarias` (ver EnviarNoticiasDiariasCommand) para no reenviar de
     * más si corre más de una vez el mismo día:
     *
     * - `enviado_at` en la programada: se marca la primera vez que se envía esa fila
     *   puntual (una sola vez en su vida, no por día — su `fecha` ya es única).
     * - `ultimo_envio_fecha` en la general: guarda la fecha calendario del último envío
     *   del mensaje general (fila única, se reenvía cada día que esté activo).
     */
    public function up(): void
    {
        Schema::table('asistencia_mensaje', function (Blueprint $table) {
            $table->timestamp('enviado_at')->nullable()->after('activo');
        });

        Schema::table('asistencia_mensaje_general', function (Blueprint $table) {
            $table->date('ultimo_envio_fecha')->nullable()->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('asistencia_mensaje', function (Blueprint $table) {
            $table->dropColumn('enviado_at');
        });

        Schema::table('asistencia_mensaje_general', function (Blueprint $table) {
            $table->dropColumn('ultimo_envio_fecha');
        });
    }
};
