<?php

namespace App\Models\DocumentosVarios;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class DocumentoVario extends Model
{
    protected $table = 'documentos_varios';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'tipo_doc',
        'id_user',
        'nombre_doc',
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
