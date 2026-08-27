<?php

namespace App\Models\Evaluaciones;

use App\Models\Usuarios\Usuario;
use App\Models\Usuarios\Nivel;
use App\Models\Usuarios\Perfil;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Evaluacion extends Model
{
    use SoftDeletes;

    protected $table = 'evaluaciones';
    protected $primaryKey = 'id';
    protected $dates = ['deleted_at'];
    public $timestamps = true;

    protected $fillable = [
        'titulo',
        'descripcion',
        'id_servicio',
        'id_user',
        'activo',
        'fecha_inicio',
        'fecha_fin',
    ];

    protected $casts = [
        'id_servicio' => 'integer',
        'id_user' => 'integer',
        'activo' => 'integer',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    protected $attributes = [
        'activo' => 1,
    ];

    public function servicio()
    {
        return $this->belongsTo(EvaluacionServicio::class, 'id_servicio');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_user', 'id_user');
    }

    public function niveles()
    {
        return $this->belongsToMany(Nivel::class, 'evaluaciones_nivel', 'id_evaluacion', 'id_nivel');
    }

    public function perfiles()
    {
        return $this->belongsToMany(Perfil::class, 'evaluaciones_perfil', 'id_evaluacion', 'id_perfil', 'id', 'id_perfil');
    }

    public function secciones()
    {
        return $this->hasMany(EvaluacionSeccion::class, 'id_evaluacion')->orderBy('orden');
    }

    public function respuestas()
    {
        return $this->hasMany(EvaluacionRespuestaEvaluacion::class, 'id_evaluacion');
    }
}
