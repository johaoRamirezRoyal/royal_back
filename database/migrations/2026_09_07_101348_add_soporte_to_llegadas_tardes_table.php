<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('llegadas_tardes', function (Blueprint $table) {
            $table->string('soporte_url')->nullable()->after('observacion');
            $table->string('soporte_public_id')->nullable()->after('soporte_url');
        });
    }

    public function down(): void
    {
        Schema::table('llegadas_tardes', function (Blueprint $table) {
            $table->dropColumn(['soporte_url', 'soporte_public_id']);
        });
    }
};
