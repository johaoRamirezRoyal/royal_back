<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instituciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            // El NIT se guarda hasheado (Hash::make), nunca en texto plano — actúa como contraseña.
            $table->string('nit');
            $table->string('email', 140)->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instituciones');
    }
};
