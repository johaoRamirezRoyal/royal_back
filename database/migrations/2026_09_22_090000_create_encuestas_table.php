<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encuestas', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->string('titulo');
            $table->string('descripcion')->nullable();
            $table->tinyInteger('activo')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encuestas');
    }
};
