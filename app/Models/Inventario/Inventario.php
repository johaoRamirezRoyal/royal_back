<?php
namespace App\Models\Inventario;

use App\Models\Areas\Areas;
use App\Models\Areas\Bloque;
use App\Models\Prestamos\PrestamosInventario;
use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

class Inventario extends Model
{
    protected $table = "inventario";

    protected $primaryKey = 'id';

    protected $fillable = [
        'descripcion',
        'marca',
        'modelo',
        'precio',
        'estado',
        'activo',
        'fecha_compra',
        'observacion',
        'id_user',
        'id_area',
        'id_bloque',
        'id_categoria',
        'user_log',
        'confirmado',
        'codigo',
        'id_compra',
        'detalles',
        'fechareg'
    ];

    protected $casts = [
        'precio' => 'float',
        'estado' => 'integer',
        'activo' => 'boolean',
        'confirmado' => 'boolean',
    ];

    public $timestamps = false;

    // Sin cast `date` a propósito: ~22k filas legacy traen '0000-00-00', que Carbon
    // convertía en "-000001-11-30T04:56:16Z", y una fecha válida salía como ISO en UTC.
    // Se expone siempre como "YYYY-MM-DD" o null. Sin fecha de compra se usa `fechareg`
    // (fecha de registro), igual que COALESCE en el listado de InventarioServices.
    protected function fechaCompra(): Attribute
    {
        $fecha = fn ($v) => $v && !str_starts_with($v, '0000-00-00') ? substr($v, 0, 10) : null;
        return Attribute::get(fn ($valor, $attrs) => $fecha($valor) ?? $fecha($attrs['fechareg'] ?? null));
    }

    // `detalles` de un ítem que entró por compra: "<id_producto>-<descripcion>-<id_compra>"
    // (antes solo "<id_producto>"). El id_producto inicial es lo que usan
    // agregarArticulosAInventario/SolicitudesServices para contar lo ya ingresado.
    public static function detalleCompra(int $idProducto, string $descripcion, int $idCompra): string
    {
        // Se recorta la descripción (no el final) para no perder el id de compra en varchar(300).
        $resto = 300 - mb_strlen("{$idProducto}--{$idCompra}");
        return "{$idProducto}-" . mb_substr($descripcion, 0, $resto) . "-{$idCompra}";
    }

    // Acepta ambos formatos: "123" (filas anteriores) y "123-..." (detalleCompra).
    public static function detalleEsDeProducto(?string $detalles, int $idProducto): bool
    {
        return $detalles === (string) $idProducto || str_starts_with((string) $detalles, "{$idProducto}-");
    }

    public function usuario(){
        return $this->belongsTo(Usuario::class, 'id_user', 'id_user');
    }

    public function area(){
        return $this->belongsTo(Areas::class, 'id_area', 'id');
    }

    public function bloque(){
        return $this->belongsTo(Bloque::class, 'id_bloque', 'id');
    }

    public function categoria(){
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id');
    }

    public function estado(){
        return $this->belongsTo(Estado::class, 'estado', 'id');
    }

    public function reportes(){
        return $this->hasMany(Reportes::class, 'id_inventario');
    }

    public function prestamos()
    {
        return $this->hasMany(PrestamosInventario::class, 'id_inventario', 'id');
    }

    public function scopeDisponiblePrestamo(
        Builder $query,
        ?string $descripcion,
        ?int $categoria
    ): Builder {
        return $query
            ->where('activo', 1)

            ->when($descripcion, function (Builder $query) use ($descripcion) {
                $query->where('descripcion', 'like', "%{$descripcion}%");
            })

            ->when($categoria, function (Builder $query) use ($categoria) {
                $query->where('id_categoria', $categoria);
            })

            ->whereDoesntHave('prestamos', function (Builder $query) {
                $query->whereNull('fecha_devolucion');
            });
    }
}