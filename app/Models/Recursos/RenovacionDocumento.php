<?php

namespace App\Models\Recursos;

use Illuminate\Database\Eloquent\Model;

class RenovacionDocumento extends Model
{
    protected $table = 'renovacion_documentos';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'tipo_proceso',
        'nom_documento',
        'version_doc',
        'fecha_vigencia',
        'fecha_revision',
        'categ_doc',
        'gestion_cambio',
        'url_archivo',
        'evidencia',
        'id_user',
        'id_log',
        'proxima_fecha',
        'tiempo_retencion',
        'activo',
        'fecha_edit',
        'fecha_inactivo',
        'id_inactiva',
    ];

    protected $casts = [
        'tipo_proceso' => 'integer',
        'categ_doc' => 'integer',
        'id_user' => 'integer',
        'id_log' => 'integer',
        'activo' => 'integer',
        'id_inactiva' => 'integer',
        'fecha_vigencia' => 'date',
        'fecha_revision' => 'date',
        'fechareg' => 'datetime',
        'fecha_edit' => 'datetime',
        'fecha_inactivo' => 'datetime',
    ];

    protected $attributes = [
        'activo' => 1,
    ];

    public function proceso()
    {
        return $this->belongsTo(ProcesoDocumento::class, 'tipo_proceso', 'id');
    }
}
