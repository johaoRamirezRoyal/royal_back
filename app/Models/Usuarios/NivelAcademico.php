<?php
namespace App\Models\Usuarios;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NivelAcademico extends Model
{
    protected $table = 'nivel_academico';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'nombre',
    ];

    public function niveles(): HasMany
    {
        return $this->hasMany(Nivel::class, 'id_nivel_academico');
    }
}
