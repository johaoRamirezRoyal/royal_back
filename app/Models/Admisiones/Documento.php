<?php

namespace App\Models\Admisiones;

use App\Models\TipoDocumento\TipoDocumento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Documento extends Model
{
    use HasFactory;

    protected $table = 'admisiones_documentos';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_inscripcion',
        'id_tipo_documento',
        'nombre_original',
        'url_archivo',
        'public_id',
        'formato',
        'peso',
        'fecha_registro',
    ];

    protected $casts = [
        'peso' => 'integer',
        'fecha_registro' => 'datetime',
    ];

    /**
     * Relación con inscripción
     */
    public function inscripcion()
    {
        return $this->belongsTo(
            Inscripcion::class,
            'id_inscripcion',
            'id'
        );
    }

    public function tipoDocumento()
    {
        return $this->belongsTo(
            TipoDocumento::class,
            'id_tipo_documento',
            'id'
        );
    }
}
