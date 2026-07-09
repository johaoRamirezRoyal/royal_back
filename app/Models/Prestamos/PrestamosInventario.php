<?php

namespace App\Models\Prestamos;

use App\Models\Inventario\Inventario;
use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class PrestamosInventario extends Model
{
    protected $table = 'prestamos_inventario';

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'id_inventario',
        'id_user_entrega',
        'id_user_recibe',
        'id_user_prestamo',
        'fecha_prestamo',
        'fecha_compromiso',
        'fecha_devolucion',
        'observacion',
        'user_log',
    ];

    const UPDATED_AT = null;

    public function inventario()
    {
        return $this->belongsTo(Inventario::class, 'id_inventario', 'id');
    }

    public function usuarioEntrega()
    {
        return $this->belongsTo(Usuario::class, 'id_user_entrega', 'id_user');
    }

    public function usuarioRecibe()
    {
        return $this->belongsTo(Usuario::class, 'id_user_recibe', 'id_user');
    }

    public function usuarioPresta()
    {
        return $this->belongsTo(Usuario::class, 'id_user_prestamo', 'id_user');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->whereNull('fecha_devolucion');
    }

    public function scopeDevueltos(Builder $query): Builder
    {
        return $query->whereNotNull('fecha_devolucion');
    }

    public function scopeVencidos(Builder $query): Builder
    {
        return $query
            ->whereNull('fecha_devolucion')
            ->where('fecha_compromiso', '<', now());
    }
}
