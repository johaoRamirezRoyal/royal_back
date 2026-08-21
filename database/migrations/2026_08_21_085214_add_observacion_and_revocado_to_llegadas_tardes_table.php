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
        Schema::table('llegadas_tardes', function (Blueprint $table) {
            $table->text('observacion')->nullable()->after('hora');
            $table->boolean('revocado')->default(false)->after('limite_alcanzado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('llegadas_tardes', function (Blueprint $table) {
            $table->dropColumn(['observacion', 'revocado']);
        });
    }
};
