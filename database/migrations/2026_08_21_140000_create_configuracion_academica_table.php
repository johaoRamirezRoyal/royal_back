<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fila única (id=1) que reemplaza el cutoff de Calendario B (1 ago - 30 jun) hardcodeado
     * en AnioEscolarServices/PeriodoAcademicoRequest. Se siembra en 'B' para no cambiar el
     * comportamiento actual hasta que alguien lo edite desde Configuración académica.
     */
    public function up(): void
    {
        Schema::create('configuracion_academica', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->enum('tipo_calendario', ['A', 'B']);
            $table->timestamp('fechareg')->useCurrent();
            $table->timestamp('fecha_updated')->nullable()->useCurrentOnUpdate();
        });

        DB::table('configuracion_academica')->insert([
            'id' => 1,
            'tipo_calendario' => 'B',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_academica');
    }
};
