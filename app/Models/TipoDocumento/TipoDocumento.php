<?php

namespace App\Models\TipoDocumento;

use App\Models\Admisiones\Documento;
use Illuminate\Database\Eloquent\Model;

class TipoDocumento extends Model
{
    protected $table = 'tipo_doc';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'activo',
        'id_log',
        'fechareg',
    ];

    protected $casts = [
        'activo' => 'integer',
        'id_log' => 'integer',
        'fechareg' => 'datetime',
    ];

    public function documentos(){
        return $this->hasMany(Documento::class, 'id_tipo_documento', 'id');
    }
}
