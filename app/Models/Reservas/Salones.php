<?php

namespace App\Models\Reservas;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Salones extends Model
{
    use HasFactory;

    protected $table = 'salones';

    protected $primaryKey = 'id';

    const CREATED_AT = 'fechareg';

    const UPDATED_AT = null;

    protected $fillable = [
        'nombre',
        'portatil',
        'sonido',
        'id_responsable',
        'id_user',
        'activo',
        'fechareg',
    ];

    protected $casts = [
        'fechareg' => 'datetime',
    ];

    public function Usuario()
    {
        return $this->hasMany(
            Usuario::class,
            'id_user',
            'id_user'
        );
    }

    /** Si tiene responsable, las reservas del salón requieren su autorización. */
    public function responsable()
    {
        return $this->belongsTo(Usuario::class, 'id_responsable', 'id_user');
    }

    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('activo', 1);
    }
}
