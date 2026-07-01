<?php

namespace App\Models\GestionAcademica;
use Illuminate\Database\Eloquent\Model;

class DiaSemana extends Model
{
    protected $table = 'dias_semana';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'abreviatura',
        'orden',
    ];

    protected $casts = [
        'id' => 'integer',
        'orden' => 'integer',
    ];
}
