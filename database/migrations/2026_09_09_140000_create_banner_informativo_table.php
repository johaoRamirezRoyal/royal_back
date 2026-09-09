<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Fila única (id=1) con el banner informativo mostrado en el login y en el sistema
 * general ya autenticado — mismo patrón de singleton que `configuracion_instituciones`.
 * Se siembra desactivado para no mostrar nada hasta que un Super Admin lo configure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banner_informativo', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->text('mensaje')->nullable();
            $table->string('variante', 20)->default('info');
            $table->boolean('activo')->default(false);
            $table->unsignedInteger('actualizado_por')->nullable();
            $table->timestamp('fechareg')->useCurrent();
            $table->timestamp('fecha_updated')->nullable()->useCurrentOnUpdate();
        });

        DB::table('banner_informativo')->insert([
            'id' => 1,
            'mensaje' => null,
            'variante' => 'info',
            'activo' => false,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('banner_informativo');
    }
};
