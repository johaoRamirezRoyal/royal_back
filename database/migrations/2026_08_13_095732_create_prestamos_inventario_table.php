<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('prestamos_inventario', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary()->index();

            $table->integer('id_inventario');
            $table->foreign('id_inventario')->references('id')->on('inventario')->cascadeOnDelete();

            $table->integer('id_user_entrega');
            $table->foreign('id_user_entrega')->references('id_user')->on('usuarios')->restrictOnDelete();

            $table->integer('id_user_recibe')->nullable();
            $table->foreign('id_user_recibe')->references('id_user')->on('usuarios')->nullOnDelete();

            $table->integer('id_user_prestamo');
            $table->foreign('id_user_prestamo')->references('id_user')->on('usuarios')->restrictOnDelete();

            $table->dateTime('fecha_prestamo');
            $table->dateTime('fecha_compromiso');
            $table->dateTime('fecha_devolucion')->nullable();

            $table->text('observacion')->nullable();
            $table->integer('user_log')->nullable();

            $table->index(['id_inventario', 'fecha_devolucion']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prestamos_inventario');
    }
};
