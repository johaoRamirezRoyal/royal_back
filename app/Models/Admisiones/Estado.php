<?php

namespace App\Models\Admisiones;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class Estado extends Model
{
    protected $table = "admisiones_estados";
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'id_log'
    ];

    protected $casts = [
        'nombre' => 'string',
        'id_log' => 'integer'
    ];

    public function inscripcion(){
        return $this->hasMany(Inscripcion::class, 'estado', 'id');
    }
    
    public function usuario()
    {
        return $this->belongsTo(
            Usuario::class,
            'id_log',
            'id_user'
        );
    }
}