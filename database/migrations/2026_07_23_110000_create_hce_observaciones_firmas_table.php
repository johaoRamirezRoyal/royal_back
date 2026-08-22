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
        Schema::create('hce_observaciones_firmas', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary()->index();

            $table->integer('id_inscripcion');
            $table->foreign('id_inscripcion')->references('id')->on('admisiones_inscripciones')->cascadeOnDelete();

            $table->text('observaciones')->nullable();
            $table->string('firma_padre')->nullable();
            $table->string('firma_madre')->nullable();
            $table->string('firma_psicologa')->nullable();

            $table->integer('updated_by')->nullable();
            $table->foreign('updated_by')->references('id_user')->on('usuarios')->nullOnDelete();

            $table->timestamp('fechareg')->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hce_observaciones_firmas');
    }
};
