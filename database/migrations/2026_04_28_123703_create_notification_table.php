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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id'); // SIN unsigned

            $table->foreign('user_id')
                ->references('id_user')
                ->on('usuarios')
                ->onDelete('cascade');
            $table->string('title');
            $table->text('message');
            $table->integer('nivel')->default(1);
            $table->integer('perfil')->default(2);
            $table->string('level')->default('info'); //info, warning, error, success
            $table->string('type')->default('informacion');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('read_at');
            $table->index('perfil');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
