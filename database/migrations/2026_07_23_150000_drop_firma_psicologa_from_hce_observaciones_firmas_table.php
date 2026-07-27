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
            $table->dropColumn('firma_psicologa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hce_observaciones_firmas', function (Blueprint $table) {
            $table->string('firma_psicologa')->nullable()->after('firma_madre_url');
        });
    }
};
