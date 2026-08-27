<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de qué dominio (de correo del usuario autenticado, ver
 * MarcaDominioService::dominioDeCorreo) hizo cada petición y a qué ruta — separado de
 * `logs_actividad` (auditoría de escrituras por usuario, vive en la base operativa, una
 * por tenant/dominio a futuro) a propósito: este es el log TRANSVERSAL para la parte
 * administrativa (gestorsami.adm.co), para saber qué dominio está pegándole a qué páginas
 * a través de todos los tenants — por eso registra toda petición (no solo
 * POST/PUT/PATCH/DELETE, ver LogDominioMiddleware) y vive en `admin_management` igual que
 * `marcas_dominio`, no en la base de ningún dominio en particular. Sin FK real a
 * `usuarios.id_user` — bases distintas, MySQL no la soporta de forma confiable acá.
 */
return new class extends Migration
{
    protected $connection = 'admin_management';

    public function up(): void
    {
        Schema::create('logs_dominio', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary()->index();
            // Null = petición sin usuario autenticado, o correo sin dominio reconocible.
            $table->string('dominio', 190)->nullable();
            $table->integer('id_user')->nullable();
            $table->string('metodo', 10);
            $table->string('ruta', 255);
            $table->smallInteger('status_code');
            $table->integer('duracion_ms');
            $table->timestamp('fechareg')->useCurrent();

            $table->index(['dominio', 'fechareg']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs_dominio');
    }
};
