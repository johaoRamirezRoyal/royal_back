<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'admin_management';

    public function up(): void
    {
        Schema::table('logs_dominio', function (Blueprint $table) {
            // Nullable por consistencia con el resto de columnas de la tabla — $request->ip()
            // no debería fallar nunca, pero el registro no debe romperse si algún día lo hace.
            $table->string('ip', 45)->nullable()->after('dominio');
        });
    }

    public function down(): void
    {
        Schema::table('logs_dominio', function (Blueprint $table) {
            $table->dropColumn('ip');
        });
    }
};
