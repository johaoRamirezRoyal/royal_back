<?php

namespace App\Models\Tramites;

use Illuminate\Database\Eloquent\Model;

/** Bitácora de correos de cambio de estado de un trámite: pendiente, enviado (aceptado por el SMTP) o error. */
class TramiteCorreo extends Model
{
    protected $table = 'tramite_correos';

    public $timestamps = false;

    protected $fillable = ['id_tramite', 'destinatarios', 'asunto', 'adjuntos', 'estado', 'error', 'intentado_at', 'enviado_at'];

    protected function casts(): array
    {
        return [
            'destinatarios' => 'array',
            'adjuntos' => 'array',
            'intentado_at' => 'datetime',
            'enviado_at' => 'datetime',
        ];
    }
}
