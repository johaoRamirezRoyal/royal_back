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
        Schema::table('hce_observaciones_firmas', function (Blueprint $table) {
            $table->string('firma_padre_url')->nullable()->after('firma_padre');
            $table->string('firma_madre_url')->nullable()->after('firma_madre');
            $table->string('firma_psicologa_url')->nullable()->after('firma_psicologa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hce_observaciones_firmas', function (Blueprint $table) {
            $table->dropColumn(['firma_padre_url', 'firma_madre_url', 'firma_psicologa_url']);
        });
    }
};
