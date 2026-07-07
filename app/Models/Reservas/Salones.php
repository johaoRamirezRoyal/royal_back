<?php

namespace App\Models\Reservas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Salones extends Model {
    use HasFactory;

    protected $table = 'salones';

    protected $primaryKey = 'id';

    const CREATED_AT = "fechareg";

    protected $fillable = [
        'nombre',
        'portatil',
        'sonido',
        'id_user',
        'activo',
        'fechareg'
    ];

    protected $casts = [
    'fechareg' => 'datetime',
];

    public function Usuario() {
        return $this->hasMany(
            \App\Models\Usuarios\Usuario::class,
            'id_user',
            'id_user'            
        );
    }
}