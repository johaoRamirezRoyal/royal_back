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
        Schema::create('logs_actividad', function (Blueprint $table) {
            $table->integer("id")->autoIncrement()->primary()->index();

            $table->integer("id_user")->nullable();
            $table->foreign("id_user")->references("id_user")->on("usuarios")->nullOnDelete();

            $table->string("metodo", 10);
            $table->string("ruta", 255);
            $table->smallInteger("status_code");
            $table->integer("duracion_ms");

            $table->timestamp("fechareg")->useCurrent();

            $table->index(["id_user", "fechareg"]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs_actividad');
    }
};
