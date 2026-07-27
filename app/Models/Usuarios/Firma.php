<?php

namespace App\Models\Usuarios;

use Illuminate\Database\Eloquent\Model;

class Firma extends Model
{
    protected $table = 'firmas';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'nombre',
        'url',
        'activo',
        'user_log',
        'fechareg',
    ];

    protected $casts = [
        'fechareg' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_user', 'id_user');
    }
}
