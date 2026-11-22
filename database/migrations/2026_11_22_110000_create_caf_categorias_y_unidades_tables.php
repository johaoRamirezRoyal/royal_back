<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cafetería, etapa 2 (catálogo): categorías de productos (jerárquicas), unidades de medida y
 * conversiones entre unidades. Los códigos los asigna el sistema (CAT-001; las unidades, de su
 * abreviatura). Siembra las unidades básicas.
 */
return new class extends Migration
{
    private const UNIDADES = [
        ['UND', 'Unidad', 'und', 0],
        ['KG', 'Kilogramo', 'kg', 3],
        ['G', 'Gramo', 'g', 0],
        ['L', 'Litro', 'L', 3],
        ['ML', 'Mililitro', 'mL', 0],
    ];

    public function up(): void
    {
        Schema::create('caf_categorias_productos', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('id_categoria_padre')->nullable()->constrained('caf_categorias_productos')->restrictOnDelete();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 120);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('caf_unidades_medida', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 60);
            $table->string('abreviatura', 12);
            // Decimales con que se registran cantidades en esta unidad (0–3, como las columnas de cantidad).
            $table->unsignedTinyInteger('precision_decimal')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('caf_conversiones_unidades', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('id_unidad_origen')->constrained('caf_unidades_medida')->restrictOnDelete();
            $table->foreignId('id_unidad_destino')->constrained('caf_unidades_medida')->restrictOnDelete();
            // 1 unidad de origen = factor unidades de destino (siempre > 0; lo valida el service).
            $table->decimal('factor_conversion', 18, 6);
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->unique(['id_unidad_origen', 'id_unidad_destino'], 'uq_caf_conversion_unidades');
        });

        $ahora = now();
        DB::table('caf_unidades_medida')->insert(array_map(fn (array $u) => [
            'codigo' => $u[0], 'nombre' => $u[1], 'abreviatura' => $u[2], 'precision_decimal' => $u[3], 'activo' => true,
            'created_at' => $ahora, 'updated_at' => $ahora,
        ], self::UNIDADES));

        $id = fn (string $codigo) => DB::table('caf_unidades_medida')->where('codigo', $codigo)->value('id');
        DB::table('caf_conversiones_unidades')->insert([
            ['id_unidad_origen' => $id('KG'), 'id_unidad_destino' => $id('G'), 'factor_conversion' => 1000, 'activa' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['id_unidad_origen' => $id('L'), 'id_unidad_destino' => $id('ML'), 'factor_conversion' => 1000, 'activa' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('caf_conversiones_unidades');
        Schema::dropIfExists('caf_unidades_medida');
        Schema::dropIfExists('caf_categorias_productos');
    }
};
