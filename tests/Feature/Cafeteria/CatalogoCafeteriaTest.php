<?php

namespace Tests\Feature\Cafeteria;

use App\Enums\TipoPerfil;
use App\Models\Cafeteria\CategoriaProducto;
use App\Models\Cafeteria\PrecioProducto;
use App\Models\Cafeteria\Producto;
use App\Models\Cafeteria\UnidadMedida;
use App\Models\Usuarios\Perfil;
use App\Models\Usuarios\Usuario;
use App\Services\Cafeteria\CatalogoCafeteriaService;
use App\Services\Cafeteria\ProveedoresCafeteriaService;
use App\Support\Permiso;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Catálogo (CatalogoCafeteriaService) y proveedores y productos (ProveedoresCafeteriaService). */
class CatalogoCafeteriaTest extends CafeteriaTestCase
{
    private CatalogoCafeteriaService $service;

    private CategoriaProducto $bebidas;

    private UnidadMedida $und;

    protected function setUp(): void
    {
        parent::setUp();

        if (! DB::getSchemaBuilder()->hasTable('caf_productos')) {
            $this->markTestSkipped('Faltan las migraciones de la etapa 2 de la cafetería.');
        }

        $this->service = app(CatalogoCafeteriaService::class);
        $this->bebidas = $this->service->crearCategoria(['nombre' => 'Bebidas'])['data'];
        $this->und = UnidadMedida::where('codigo', 'UND')->firstOrFail();
    }

    public function test_codigos_del_sistema_para_categorias_productos_y_unidades(): void
    {
        $this->assertMatchesRegularExpression('/^CAT-\d{3,}$/', $this->bebidas->codigo);
        $this->assertMatchesRegularExpression('/^PRD-\d{4,}$/', $this->producto()->codigo);

        // La unidad toma el código de la abreviatura; si se repite, lleva sufijo.
        $libra = $this->service->crearUnidad(['nombre' => 'Libra', 'abreviatura' => 'lb', 'precision_decimal' => 2])['data'];
        $this->assertSame('LB', $libra->codigo);
        $otra = $this->service->crearUnidad(['nombre' => 'Libra inglesa', 'abreviatura' => 'lb', 'precision_decimal' => 2])['data'];
        $this->assertSame('LB_2', $otra->codigo);
    }

    public function test_una_categoria_no_queda_dentro_de_si_misma_ni_de_su_hija(): void
    {
        $gaseosas = $this->service->crearCategoria(['nombre' => 'Gaseosas', 'id_categoria_padre' => $this->bebidas->id])['data'];

        $this->assertSame(422, $this->service->actualizarCategoria($this->bebidas->id, ['nombre' => 'Bebidas', 'id_categoria_padre' => $this->bebidas->id])['status']);
        $this->assertSame(422, $this->service->actualizarCategoria($this->bebidas->id, ['nombre' => 'Bebidas', 'id_categoria_padre' => $gaseosas->id])['status']);
        $this->assertFalse($this->service->actualizarCategoria($gaseosas->id, ['nombre' => 'Gaseosas', 'id_categoria_padre' => null])['error']);
    }

    public function test_no_desactiva_una_categoria_ni_una_unidad_con_productos_activos(): void
    {
        $this->producto();

        $this->assertTrue($this->service->actualizarCategoria($this->bebidas->id, ['nombre' => 'Bebidas', 'activo' => false])['error']);
        $this->assertTrue($this->service->actualizarUnidad($this->und->id, ['nombre' => 'Unidad', 'abreviatura' => 'und', 'precision_decimal' => 0, 'activo' => false])['error']);
    }

    public function test_un_producto_se_vende_o_controla_inventario(): void
    {
        $r = $this->service->crearProducto($this->datosProducto(['es_vendible' => false, 'controla_inventario' => false]), 1);
        $this->assertSame(422, $r['status']);
    }

    public function test_conversiones_sin_repetir_ni_invertir(): void
    {
        $kg = UnidadMedida::where('codigo', 'KG')->firstOrFail();
        $g = UnidadMedida::where('codigo', 'G')->firstOrFail();

        // KG→G viene sembrada: ni la misma ni la inversa.
        $this->assertTrue($this->service->crearConversion(['id_unidad_origen' => $g->id, 'id_unidad_destino' => $kg->id, 'factor_conversion' => 0.001])['error']);
        $this->assertTrue($this->service->crearConversion(['id_unidad_origen' => $kg->id, 'id_unidad_destino' => $kg->id, 'factor_conversion' => 1])['error']);
    }

    public function test_un_precio_nuevo_cierra_el_abierto_y_no_se_cruza_con_otros(): void
    {
        $producto = $this->producto();
        $hoy = CarbonImmutable::today();

        $actual = $this->service->agregarPrecio($producto->id, ['precio' => 3000, 'vigencia_desde' => $hoy->toDateString()], 1)['data'];
        $nuevo = $this->service->agregarPrecio($producto->id, ['precio' => 3500, 'vigencia_desde' => $hoy->addDays(10)->toDateString()], 1);
        $this->assertFalse($nuevo['error']);
        $this->assertSame($hoy->addDays(9)->toDateString(), $actual->refresh()->vigencia_hasta->format('Y-m-d'));

        // Un tramo cerrado que cae dentro de otro precio se rechaza.
        $cruce = $this->service->agregarPrecio($producto->id, ['precio' => 1, 'vigencia_desde' => $hoy->addDays(2)->toDateString(), 'vigencia_hasta' => $hoy->addDays(4)->toDateString()], 1);
        $this->assertSame(422, $cruce['status']);

        // El de un punto de venta es otro alcance: no choca con el general.
        $punto = DB::table('caf_puntos_venta as p')->join('caf_cafeterias as c', 'c.id', '=', 'p.id_cafeteria')->where('c.estado', '!=', 'CERRADA')->value('p.id');
        if ($punto) {
            $this->assertFalse($this->service->agregarPrecio($producto->id, ['id_punto_venta' => $punto, 'precio' => 2800, 'vigencia_desde' => $hoy->toDateString()], 1)['error']);
        }
    }

    public function test_precios_solo_desde_hoy_y_de_productos_vendibles(): void
    {
        $ayer = CarbonImmutable::yesterday()->toDateString();
        $this->assertSame(422, $this->service->agregarPrecio($this->producto()->id, ['precio' => 1000, 'vigencia_desde' => $ayer], 1)['status']);

        $harina = $this->producto(['nombre' => 'Harina', 'tipo_producto' => 'MATERIA_PRIMA', 'es_vendible' => false]);
        $this->assertSame(422, $this->service->agregarPrecio($harina->id, ['precio' => 1000, 'vigencia_desde' => CarbonImmutable::today()->toDateString()], 1)['status']);
    }

    public function test_borrar_un_precio_futuro_devuelve_el_tramo_al_anterior(): void
    {
        $producto = $this->producto();
        $hoy = CarbonImmutable::today();
        $actual = $this->service->agregarPrecio($producto->id, ['precio' => 3000, 'vigencia_desde' => $hoy->toDateString()], 1)['data'];
        $futuro = $this->service->agregarPrecio($producto->id, ['precio' => 3500, 'vigencia_desde' => $hoy->addDays(5)->toDateString()], 1)['data'];

        $this->assertTrue($this->service->eliminarPrecio($actual->id)['error'], 'Borró un precio que ya rige');
        $this->assertFalse($this->service->eliminarPrecio($futuro->id)['error']);
        $this->assertNull($actual->refresh()->vigencia_hasta);
        $this->assertSame(1, PrecioProducto::where('id_producto', $producto->id)->count());
    }

    public function test_una_promocion_no_deja_al_producto_sin_precio(): void
    {
        $producto = $this->producto();
        $hoy = CarbonImmutable::today();
        $actual = $this->service->agregarPrecio($producto->id, ['precio' => 3500, 'vigencia_desde' => $hoy->toDateString()], 1)['data'];
        $promo = $this->service->agregarPrecio($producto->id, ['precio' => 3000, 'vigencia_desde' => $hoy->addDays(3)->toDateString(), 'vigencia_hasta' => $hoy->addDays(8)->toDateString()], 1)['data'];

        // El precio de antes rige hasta la promoción y vuelve a regir, abierto, el día después.
        $tramos = PrecioProducto::where('id_producto', $producto->id)->orderBy('vigencia_desde')->get()
            ->map(fn ($p) => [(float) $p->precio, $p->vigencia_desde->format('Y-m-d'), $p->vigencia_hasta?->format('Y-m-d')])->all();
        $this->assertSame([
            [3500.0, $hoy->toDateString(), $hoy->addDays(2)->toDateString()],
            [3000.0, $hoy->addDays(3)->toDateString(), $hoy->addDays(8)->toDateString()],
            [3500.0, $hoy->addDays(9)->toDateString(), null],
        ], $tramos);

        // Borrar la promoción devuelve su tramo al anterior: sin huecos.
        $this->assertFalse($this->service->eliminarPrecio($promo->id)['error']);
        $this->assertSame($hoy->addDays(8)->toDateString(), $actual->refresh()->vigencia_hasta->format('Y-m-d'));
    }

    public function test_una_subcategoria_archivada_bajo_un_padre_inactivo_se_puede_editar(): void
    {
        $jugos = $this->service->crearCategoria(['nombre' => 'Jugos', 'id_categoria_padre' => $this->bebidas->id])['data'];
        $this->assertFalse($this->service->actualizarCategoria($jugos->id, ['nombre' => 'Jugos', 'id_categoria_padre' => $this->bebidas->id, 'activo' => false])['error']);
        $this->assertFalse($this->service->actualizarCategoria($this->bebidas->id, ['nombre' => 'Bebidas', 'id_categoria_padre' => null, 'activo' => false])['error']);

        $this->assertFalse($this->service->actualizarCategoria($jugos->id, ['nombre' => 'Jugos naturales', 'id_categoria_padre' => $this->bebidas->id, 'activo' => false])['error']);
        // Reactivarla sí exige un padre activo.
        $this->assertSame(422, $this->service->actualizarCategoria($jugos->id, ['nombre' => 'Jugos naturales', 'id_categoria_padre' => $this->bebidas->id, 'activo' => true])['status']);
    }

    public function test_proveedores_un_vinculo_por_producto_y_un_solo_preferido(): void
    {
        $proveedores = Usuario::where('estado', 'activo')->whereIn('perfil', Perfil::idsDeTipo(TipoPerfil::PROVEEDOR))->limit(2)->pluck('id_user');
        if ($proveedores->count() < 2) {
            $this->markTestSkipped('Faltan dos proveedores activos.');
        }
        $servicio = app(ProveedoresCafeteriaService::class);
        $producto = $this->producto();

        $a = $servicio->vincular(['id_proveedor' => $proveedores[0], 'id_producto' => $producto->id, 'es_preferido' => true])['data'];
        $this->assertTrue($servicio->vincular(['id_proveedor' => $proveedores[0], 'id_producto' => $producto->id])['error'], 'Vinculó dos veces');

        $b = $servicio->vincular(['id_proveedor' => $proveedores[1], 'id_producto' => $producto->id, 'es_preferido' => true])['data'];
        $this->assertTrue($b->es_preferido);
        $this->assertFalse($a->refresh()->es_preferido);

        // Desactivado deja de ser el preferido.
        $this->assertFalse($servicio->actualizarVinculo($b->id, ['activo' => false])['data']->es_preferido);

        // Un docente no es proveedor.
        $docente = Usuario::where('estado', 'activo')->whereIn('perfil', Perfil::idsDeTipo(TipoPerfil::DOCENTE))->value('id_user');
        if ($docente) {
            $this->assertTrue($servicio->vincular(['id_proveedor' => $docente, 'id_producto' => $producto->id])['error']);
        }
    }

    public function test_la_api_rechaza_a_quien_no_tiene_las_opciones_178_ni_179(): void
    {
        $sinOpcion = Usuario::where('estado', 'activo')->where('perfil', '<>', Permiso::PERFIL_SUPER_ADMIN)->first()
            ?? $this->markTestSkipped('Falta un usuario activo que no sea Super Admin.');
        DB::table('cron_permisos')->whereIn('id_opcion', [Permiso::CAFETERIA_CATALOGO, Permiso::CAFETERIA_PROVEEDORES])->delete();

        $this->getJson('/api/cafeteria/productos', $this->auth($sinOpcion))->assertForbidden();
        $this->getJson('/api/cafeteria/catalogo/catalogos', $this->auth($sinOpcion))->assertForbidden();
        $this->getJson('/api/cafeteria/proveedores', $this->auth($sinOpcion))->assertForbidden();
    }

    public function test_la_api_crea_un_producto_con_codigo_del_sistema(): void
    {
        $superAdmin = Usuario::where('estado', 'activo')->where('perfil', Permiso::PERFIL_SUPER_ADMIN)->first()
            ?? $this->markTestSkipped('Falta un Super Admin activo.');

        $creado = $this->postJson('/api/cafeteria/productos', [...$this->datosProducto(), 'codigo' => 'MIO'], $this->auth($superAdmin))
            ->assertOk()
            ->json('data');
        $this->assertMatchesRegularExpression('/^PRD-\d{4,}$/', $creado['codigo']);
        $this->assertSame((int) $superAdmin->id_user, $creado['creado_por']);

        $this->getJson("/api/cafeteria/productos/{$creado['id']}", $this->auth($superAdmin))
            ->assertOk()
            ->assertJsonPath('data.categoria.nombre', 'Bebidas');
    }

    private function datosProducto(array $cambios = []): array
    {
        return [
            'id_categoria' => $this->bebidas->id,
            'id_unidad_base' => $this->und->id,
            'nombre' => 'Jugo de naranja',
            'tipo_producto' => 'REVENTA',
            'es_vendible' => true,
            'controla_inventario' => true,
            ...$cambios,
        ];
    }

    private function producto(array $cambios = []): Producto
    {
        $r = $this->service->crearProducto($this->datosProducto($cambios), 1);
        $this->assertFalse($r['error'], $r['message']);

        return $r['data'];
    }
}
