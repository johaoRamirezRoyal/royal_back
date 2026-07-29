<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nombre legible por terminal Hikvision, editable desde el frontend
     * (en vez de depender solo de HIKVISION_DEVICE_NAME/HIKVISION_HOSTS en .env).
     * device_id es el mismo id "host:port" que ya usa hikvisionattendanceService.
     */
    public function up(): void
    {
        Schema::create('hikvision_dispositivos', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->string('device_id', 100)->unique();
            $table->string('nombre', 100);

            $table->timestamp('fechareg')->useCurrent();
            $table->timestamp('fecha_updated')->nullable()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hikvision_dispositivos');
    }
};
