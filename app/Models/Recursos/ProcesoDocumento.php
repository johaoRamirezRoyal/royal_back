<?php

namespace App\Models\Recursos;

use Illuminate\Database\Eloquent\Model;

class ProcesoDocumento extends Model
{
    protected $table = 'procesos_documento';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'id_log',
        'activo',
    ];

    protected $casts = [
        'id_log' => 'integer',
        'activo' => 'integer',
        'fechareg' => 'datetime',
    ];

    protected $attributes = [
        'activo' => 1,
    ];

    public function documentos()
    {
        return $this->hasMany(RenovacionDocumento::class, 'tipo_proceso', 'id');
    }
}
