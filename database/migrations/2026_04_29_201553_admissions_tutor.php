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
        Schema::create('admission_tutors', function(Blueprint $table) {
            $table->id();
            $table->string('email', 150)->index()->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('phone_mumber', 15);
            $table->string('address', 255);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_tutor');
    }
};
