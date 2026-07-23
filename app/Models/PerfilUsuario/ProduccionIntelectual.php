<?php

namespace App\Models\PerfilUsuario;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class ProduccionIntelectual extends Model
{
    protected $table = 'produccion_intelectual';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'tipo_produccion',
        'denominacion',
        'nombre',
        'objetivo',
        'descripcion_actividades',
        'duracion',
        'lugar',
        'observacion',
        'evidencia_pdf',
        'fechareg',
    ];

    protected $casts = [
        'id_user' => 'integer',
        'fechareg' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_user', 'id_user');
    }
}
