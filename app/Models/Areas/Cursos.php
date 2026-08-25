<?php

namespace App\Models\Areas;

use App\Models\Usuarios\NivelAcademico;
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

    // id_nivel apunta a nivel_academico (no a `nivel`) desde
    // 2026_08_25_030000_migrate_curso_and_esquema_nivel_to_nivel_academico — lo académico
    // usa nivel_academico, `nivel` es solo para clasificar usuarios y otros módulos.
    public function nivel()
    {
        return $this->belongsTo(NivelAcademico::class, 'id_nivel', 'id');
    }

    public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'id_curso', 'id');
    }
}
