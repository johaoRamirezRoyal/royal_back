<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'admin_management';

    /**
     * Identidad de cada institución en una sola tabla (`marcas`): nombre, lema, color, logo,
     * logo secundario e imagen de fondo. `marcas_dominio` queda como dominio de correo → marca
     * y `colegios_admision` como slug de URL → marca (Admisiones, portal para padres). Antes
     * cada tabla tenía su copia y no coincidían (Royal School tenía dos logos y dos colores).
     *
     * Las columnas viejas de identidad NO se borran todavía: la base es compartida y el código
     * anterior sigue leyéndolas mientras se despliega. Ya no se escriben; se borran en una
     * migración aparte. Se vuelven nullable porque las filas nuevas no las llenan.
     *
     * Datos: cada marca por dominio se copia a `marcas`. Cada colegio se une a la marca con su
     * mismo nombre (Royal School ↔ royalschool.edu.co): se conservan los valores de la marca por
     * dominio y solo se completan los que le faltan (p. ej. el logo secundario, que solo tenía el
     * colegio). Un colegio sin marca con su nombre crea la suya con sus propios valores.
     */
    public function up(): void
    {
        Schema::create('marcas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre', 190);
            $table->string('descripcion', 190)->nullable();
            $table->string('color', 7)->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->string('logo_public_id', 255)->nullable();
            $table->string('logo_secundario_path', 255)->nullable();
            $table->string('logo_secundario_public_id', 255)->nullable();
            $table->string('imagen_fondo_path', 255)->nullable();
            $table->string('imagen_fondo_public_id', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('fechareg')->useCurrent();
            $table->timestamp('fecha_updated')->nullable();
        });

        Schema::table('marcas_dominio', function (Blueprint $table) {
            $table->unsignedInteger('id_marca')->nullable()->after('dominio');
            $table->foreign('id_marca')->references('id')->on('marcas')->cascadeOnDelete();
            $table->string('logo_path', 255)->nullable()->change();
            $table->string('logo_public_id', 255)->nullable()->change();
        });

        Schema::table('colegios_admision', function (Blueprint $table) {
            $table->unsignedInteger('id_marca')->nullable()->after('connection');
            $table->foreign('id_marca')->references('id')->on('marcas')->nullOnDelete();
            $table->string('nombre', 190)->nullable()->change();
        });

        $db = DB::connection('admin_management');
        $identidad = ['nombre', 'descripcion', 'color', 'logo_path', 'logo_public_id', 'logo_secundario_path', 'logo_secundario_public_id', 'imagen_fondo_path', 'imagen_fondo_public_id'];

        foreach ($db->table('marcas_dominio')->orderBy('id')->get() as $dominio) {
            $idMarca = $db->table('marcas')->insertGetId([
                'nombre' => $dominio->nombre ?: $dominio->dominio,
                'descripcion' => $dominio->descripcion ?: null,
                'color' => $dominio->color ?: null,
                'logo_path' => $dominio->logo_path ?: null,
                'logo_public_id' => $dominio->logo_public_id ?: null,
                'imagen_fondo_path' => $dominio->imagen_fondo_path ?: null,
                'imagen_fondo_public_id' => $dominio->imagen_fondo_public_id ?: null,
                'activo' => $dominio->activo,
                'fechareg' => $dominio->fechareg,
            ]);
            $db->table('marcas_dominio')->where('id', $dominio->id)->update(['id_marca' => $idMarca]);
        }

        foreach ($db->table('colegios_admision')->orderBy('id')->get() as $colegio) {
            $propios = [
                'nombre' => $colegio->nombre,
                'descripcion' => $colegio->descripcion ?: null,
                // colegios_admision.color es varchar(20); marcas.color guarda "#RRGGBB".
                'color' => $colegio->color ? substr($colegio->color, 0, 7) : null,
                'logo_path' => $colegio->logo_path ?: null,
                'logo_public_id' => $colegio->logo_public_id ?: null,
                'logo_secundario_path' => $colegio->logo_secundario_path ?: null,
                'logo_secundario_public_id' => $colegio->logo_secundario_public_id ?: null,
            ];

            $marca = $db->table('marcas')->whereRaw('LOWER(TRIM(nombre)) = ?', [mb_strtolower(trim((string) $colegio->nombre))])->orderBy('id')->first();

            if ($marca) {
                // Gana la marca por dominio: solo se llena lo que le falta.
                $faltantes = [];
                foreach ($identidad as $campo) {
                    if (empty($marca->{$campo}) && ! empty($propios[$campo] ?? null)) {
                        $faltantes[$campo] = $propios[$campo];
                    }
                }
                if ($faltantes) {
                    $db->table('marcas')->where('id', $marca->id)->update($faltantes);
                }
                $idMarca = $marca->id;
            } else {
                $idMarca = $db->table('marcas')->insertGetId($propios + ['activo' => $colegio->activo, 'fechareg' => $colegio->fechareg]);
            }

            $db->table('colegios_admision')->where('id', $colegio->id)->update(['id_marca' => $idMarca]);
        }
    }

    public function down(): void
    {
        Schema::table('colegios_admision', function (Blueprint $table) {
            $table->dropForeign(['id_marca']);
            $table->dropColumn('id_marca');
        });

        Schema::table('marcas_dominio', function (Blueprint $table) {
            $table->dropForeign(['id_marca']);
            $table->dropColumn('id_marca');
        });

        Schema::dropIfExists('marcas');
    }
};
