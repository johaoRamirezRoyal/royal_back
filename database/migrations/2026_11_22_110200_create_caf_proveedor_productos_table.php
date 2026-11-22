<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cafetería, etapa 2 (catálogo): qué productos ofrece cada proveedor. El proveedor es el de
 * Compras (decisión C1): un usuario de tipo PROVEEDOR con su `proveedor_detalle`, así que
 * `id_proveedor` = `usuarios.id_user` (int, sin FK, como el resto de referencias a usuarios).
 * No se crean `proveedores` ni `proveedor_usuarios`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caf_proveedor_productos', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->integer('id_proveedor')->index();
            $table->foreignId('id_producto')->constrained('caf_productos')->restrictOnDelete();
            $table->string('codigo_producto_proveedor', 60)->nullable();
            $table->decimal('precio_referencia', 14, 2)->nullable();
            $table->decimal('cantidad_minima', 14, 3)->nullable();
            $table->boolean('es_preferido')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['id_proveedor', 'id_producto']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caf_proveedor_productos');
    }
};
