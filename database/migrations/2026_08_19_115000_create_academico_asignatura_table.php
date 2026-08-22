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
        Schema::create('academico_asignatura', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary()->index();

            $table->string('nombre', 255);
            $table->string('codigo', 50)->unique();
            $table->string('abreviatura', 20);
            $table->string('color', 20);
            $table->boolean('activo')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academico_asignatura');
    }
};
