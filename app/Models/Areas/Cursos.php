<?php

namespace App\Models\Areas;

use App\Models\Usuarios\Nivel;
use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class Cursos extends Model
{
    protected $table = "curso";

    protected $primaryKey = 'id';

    public $timestamps = false;

    public $fillable = [
        "nombre",
        "id_nivel",
        "curso_proximo",
        "activo",
        "id_log"
    ];

    public function nivel()
    {
        return $this->belongsTo(Nivel::class, 'id_nivel', 'id');
    }

    public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'id_curso', 'id');
    }
}
