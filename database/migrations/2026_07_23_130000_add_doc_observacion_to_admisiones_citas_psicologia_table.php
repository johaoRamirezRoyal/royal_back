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
        Schema::table('admisiones_citas_psicologia', function (Blueprint $table) {
            $table->string('doc_observacion')->nullable()->after('observaciones');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admisiones_citas_psicologia', function (Blueprint $table) {
            $table->dropColumn('doc_observacion');
        });
    }
};
