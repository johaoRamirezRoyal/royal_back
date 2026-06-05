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
        Schema::create('admisiones_estados', function (Blueprint $table) {
            $table->integer("id")->autoIncrement()->primary()->index();
            $table->string("nombre", 100)->unique();
            $table->boolean("estado")->default(true);

            $table->integer("id_log")->nullable();
            $table->foreign("id_log")->references("id_user")->on("usuarios")->cascadeOnDelete();

            $table->timestamp("fechareg")->useCurrent();
            $table->timestamp("fecha_updated")->nullable()->useCurrentOnUpdate();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admisiones_estados');
    }
};
