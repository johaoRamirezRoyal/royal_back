<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hce_observaciones_firmas', function (Blueprint $table) {
            $table->integer('id_firma_psicologa')->nullable()->after('firma_psicologa_url');
            $table->foreign('id_firma_psicologa')->references('id')->on('firmas')->nullOnDelete();
        });

        DB::table('hce_observaciones_firmas as hof')
            ->join('firmas as f', 'f.url', '=', 'hof.firma_psicologa_url')
            ->whereNotNull('hof.firma_psicologa_url')
            ->update(['hof.id_firma_psicologa' => DB::raw('f.id')]);

        Schema::table('hce_observaciones_firmas', function (Blueprint $table) {
            $table->dropColumn('firma_psicologa_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hce_observaciones_firmas', function (Blueprint $table) {
            $table->string('firma_psicologa_url')->nullable()->after('firma_madre_url');
        });

        DB::table('hce_observaciones_firmas as hof')
            ->join('firmas as f', 'f.id', '=', 'hof.id_firma_psicologa')
            ->update(['hof.firma_psicologa_url' => DB::raw('f.url')]);

        Schema::table('hce_observaciones_firmas', function (Blueprint $table) {
            $table->dropForeign(['id_firma_psicologa']);
            $table->dropColumn('id_firma_psicologa');
        });
    }
};
