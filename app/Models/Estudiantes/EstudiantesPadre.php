<?php

namespace App\Models\Estudiantes;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class EstudiantesPadre extends Model
{
    protected $table = 'estudiantes_padres';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_estudiante',
        'id_acudiente',
        'lugar_expedicion',
        'direccion',
        'ciudad',
        'celular',
        'responsable',
        'activo',
        'fechareg',
    ];

    protected $casts = [
        'id_estudiante' => 'integer',
        'id_acudiente' => 'integer',
        'responsable' => 'integer',
        'activo' => 'integer',
        'fechareg' => 'datetime',
    ];

    protected $attributes = [
        'responsable' => 0,
        'activo' => 1,
    ];

    public function estudiante()
    {
        return $this->belongsTo(Usuario::class, 'id_estudiante', 'id_user');
    }

    public function acudiente()
    {
        return $this->belongsTo(Usuario::class, 'id_acudiente', 'id_user');
    }
}
