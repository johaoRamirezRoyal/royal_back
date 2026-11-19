<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Qué texto del propósito sale como título en el boletín de notas (referentes): su nombre
     * (por defecto, como hasta ahora) o su descripción. Una sola opción para toda la institución,
     * en Configuración académica → Propósitos.
     */
    public function up(): void
    {
        Schema::table('configuracion_academica', function (Blueprint $table) {
            $table->enum('titulo_proposito_boletin', ['NOMBRE', 'DESCRIPCION'])->default('NOMBRE')->after('tipo_calendario');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_academica', function (Blueprint $table) {
            $table->dropColumn('titulo_proposito_boletin');
        });
    }
};
