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
        Schema::table('logs_actividad', function (Blueprint $table) {
            // TEXT porque el cifrado (cast 'encrypted') produce un payload mucho más largo que una IPv4/IPv6.
            $table->text('ip')->nullable()->after('id_user');
            $table->string('pais', 100)->nullable()->after('ip');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('logs_actividad', function (Blueprint $table) {
            $table->dropColumn(['ip', 'pais']);
        });
    }
};
