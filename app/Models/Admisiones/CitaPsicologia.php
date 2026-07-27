<?php

namespace App\Models\Admisiones;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class CitaPsicologia extends Model
{
    protected $table = 'admisiones_citas_psicologia';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_inscripcion',
        'id_psicologa',
        'fecha_cita',
        'observaciones',
        'estado_cita',
        'doc_observacion',
    ];

    protected $casts = [
        'fecha_cita' => 'datetime',
    ];

    public function inscripcion()
    {
        return $this->belongsTo(Inscripcion::class, 'id_inscripcion', 'id');
    }

    public function psicologa()
    {
        return $this->belongsTo(Usuario::class, 'id_psicologa', 'id_user');
    }
}
