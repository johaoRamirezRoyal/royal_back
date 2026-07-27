<?php

namespace App\Models\Enfermeria;

use App\Models\Estudiantes\EstudiantesPadre;
use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class EnfermeriaAtencion extends Model
{
    protected $table = 'enfermeria_atencion';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'motivo',
        'tratamiento',
        'envio',
        'enviado',
        'id_log',
        'fechareg',
    ];

    protected $casts = [
        'id_user' => 'integer',
        'motivo' => 'integer',
        'envio' => 'integer',
        'enviado' => 'integer',
        'fechareg' => 'datetime',
    ];

    protected $attributes = [
        'envio' => 0,
        'enviado' => 0,
    ];

    /**
     * El estudiante atendido.
     */
    public function estudiante()
    {
        return $this->belongsTo(Usuario::class, 'id_user', 'id_user');
    }

    /**
     * Categoría/motivo de la atención.
     */
    public function categoria()
    {
        return $this->belongsTo(EnfermeriaCategoria::class, 'motivo', 'id');
    }

    /**
     * Quién registró la atención.
     */
    public function registradoPor()
    {
        return $this->belongsTo(Usuario::class, 'id_log', 'id_user');
    }

    /**
     * Acudientes del estudiante a través de la tabla pivote.
     */
    public function acudientes()
    {
        return $this->belongsToMany(
            Usuario::class,
            EstudiantesPadre::class,
            'id_estudiante',
            'id_acudiente',
            'id_user',
            'id_user'
        )->wherePivot('activo', 1);
    }
}
