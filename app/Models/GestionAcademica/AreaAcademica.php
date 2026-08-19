<?php

namespace App\Models\GestionAcademica;

use Illuminate\Database\Eloquent\Model;

class AreaAcademica extends Model
{
    protected $table = 'academico_area';

    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre',
        'activo',
    ];

    protected $attributes = [
        'activo' => 1,
    ];

    public function asignaturas()
    {
        return $this->hasMany(Asignatura::class, 'id_area');
    }
}
