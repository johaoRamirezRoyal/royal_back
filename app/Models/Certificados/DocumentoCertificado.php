<?php

namespace App\Models\Certificados;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla legacy `documentos` (id, nombre, id_sol, id_log, id_user, anio_mes, fechareg) —
 * en este dominio se usa solo para el archivo final de una solicitud de certificado
 * (`id_sol` = sol_certificados.id).
 */
class DocumentoCertificado extends Model
{
    protected $table = 'documentos';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'id_sol',
        'id_log',
        'id_user',
        'anio_mes',
    ];

    protected $casts = [
        'id_sol' => 'integer',
        'id_log' => 'integer',
        'id_user' => 'integer',
        'fechareg' => 'datetime',
    ];
}
