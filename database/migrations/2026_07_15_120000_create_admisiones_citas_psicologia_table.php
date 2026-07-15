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
        Schema::create('admisiones_citas_psicologia', function (Blueprint $table) {
            $table->integer("id")->autoIncrement()->primary()->index();

            $table->integer("id_inscripcion");
            $table->foreign("id_inscripcion")->references("id")->on("admisiones_inscripciones")->cascadeOnDelete();

            $table->integer("id_psicologa");
            $table->foreign("id_psicologa")->references("id_user")->on("usuarios")->cascadeOnDelete();

            $table->dateTime("fecha_cita");
            $table->text("observaciones")->nullable();

            $table->timestamp("fechareg")->useCurrent();
            $table->timestamp("fecha_updated")->nullable()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admisiones_citas_psicologia');
    }
};
