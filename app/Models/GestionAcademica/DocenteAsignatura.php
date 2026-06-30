<?php

namespace App\Models\GestionAcademica;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class DocenteAsignatura extends Model
{
    protected $table = 'academico_docente_asignatura';

    protected $primaryKey = 'id';

    protected $fillable = [
        'id_docente',
        'id_asignatura',
        'activo',
    ];

    protected $attributes = [
        'activo' => 1,
    ];

    public function docente()
    {
        return $this->belongsTo(Usuario::class, 'id_docente', 'id_user');
    }

    public function asignatura()
    {
        return $this->belongsTo(Asignatura::class, 'id_asignatura', 'id');
    }
}
