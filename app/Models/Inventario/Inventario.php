<?php
namespace App\Models\Inventario;

use App\Models\Areas\Areas;
use App\Models\Prestamos\PrestamosInventario;
use App\Models\Usuarios\Usuario;
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
        'id_categoria',
        'user_log',
        'confirmado',
        'codigo',
        'id_compra',
        'detalles'
    ];

    protected $casts = [
        'precio' => 'float',
        'estado' => 'integer',
        'activo' => 'boolean',
        'confirmado' => 'boolean',
        'fecha_compra' => 'date',
    ];

    public $timestamps = false;

    public function usuario(){
        return $this->belongsTo(Usuario::class, 'id_user', 'id_user');
    }

    public function area(){
        return $this->belongsTo(Areas::class, 'id_area', 'id');
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