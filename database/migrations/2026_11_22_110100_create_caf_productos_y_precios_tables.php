<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cafetería, etapa 2 (catálogo): productos y sus precios con vigencia.
 * - `codigo` es el SKU del modelo: lo asigna el sistema (PRD-0001) y no cambia.
 * - `tipo_producto`: REVENTA, PREPARADO, MATERIA_PRIMA o INSUMO (App\Enums\Cafeteria\TipoProducto).
 *   `es_vendible` y `controla_inventario` van aparte: un preparado se vende sin inventario propio
 *   y una materia prima tiene inventario sin venderse.
 * - Precio: `id_punto_venta` NULL = precio general de la institución; uno por punto lo
 *   reemplaza en ese punto. Las vigencias de un mismo producto y punto no se cruzan: lo valida
 *   el service con bloqueo de filas (un UNIQUE con NULL no lo garantiza en MySQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caf_productos', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('id_categoria')->constrained('caf_categorias_productos')->restrictOnDelete();
            $table->foreignId('id_unidad_base')->constrained('caf_unidades_medida')->restrictOnDelete();
            $table->string('codigo', 30)->unique();
            $table->string('codigo_barras', 60)->nullable()->unique();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->string('tipo_producto', 20)->index();
            $table->boolean('controla_inventario')->default(true);
            $table->boolean('es_vendible')->default(true);
            $table->boolean('activo')->default(true);
            $table->integer('creado_por')->index();
            $table->timestamps();
        });

        Schema::create('caf_precios_productos', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('id_producto')->constrained('caf_productos')->restrictOnDelete();
            $table->foreignId('id_punto_venta')->nullable()->constrained('caf_puntos_venta')->restrictOnDelete();
            $table->decimal('precio', 14, 2);
            $table->date('vigencia_desde');
            $table->date('vigencia_hasta')->nullable();
            $table->integer('creado_por')->index();
            $table->timestamps();

            $table->index(['id_producto', 'id_punto_venta', 'vigencia_desde', 'vigencia_hasta'], 'idx_caf_precios_vigencia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caf_precios_productos');
        Schema::dropIfExists('caf_productos');
    }
};
