<?php
namespace App\Models\Admisiones;
use App\Models\Admisiones\Aspirante;
use App\Models\AnioEscolar\Anio;
use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class Inscripcion extends Model
{
    protected $table = 'admisiones_inscripciones';

    protected $primaryKey = 'id';

    public $timestamps = false;

    /**
     * Summary of fillable
     * @var array
     * @var array<estado> Debe ser un array del tipo: 
        'BORRADOR',
        'PENDIENTE',
        'EN_REVISION',
        'APROBADO',
        'RECHAZADO',
        'CANCELADO'
     */
    protected $fillable = [
        'codigo',
        'estado',
        'anio_academico',
        'id_usuario_registro',
        'fecha_inscripcion'
    ];

    public function aspirante()
    {
        return $this->hasOne(Aspirante::class, 'id_inscripcion', 'id');
    }

    public function usuarioRegistro()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_registro', 'id_user');
    }

    public function anioAcademico(){
        return $this->belongsTo(Anio::class, 'anio_academico', 'id');
    }
}
