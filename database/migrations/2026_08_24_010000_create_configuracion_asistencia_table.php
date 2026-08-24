<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fila única (id=1) que reemplaza la constante hardcodeada
     * AsistenciaGestionService::HORA_MINIMA_SALIDA_DEFECTO ('09:00:00'), el fallback que se
     * usa cuando ningún horario configurado aplica al grupo/día del usuario (ver
     * AsistenciaGestionService::horaMinimaSalidaParaUsuario). Se siembra con ese mismo valor
     * para no cambiar el comportamiento actual hasta que alguien lo edite desde
     * Configuración de asistencia.
     */
    public function up(): void
    {
        Schema::create('configuracion_asistencia', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->time('hora_minima_salida_defecto');
            $table->timestamp('fechareg')->useCurrent();
            $table->timestamp('fecha_updated')->nullable()->useCurrentOnUpdate();
        });

        DB::table('configuracion_asistencia')->insert([
            'id' => 1,
            'hora_minima_salida_defecto' => '09:00:00',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_asistencia');
    }
};
