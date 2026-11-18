<?php

namespace App\Models\FotosCurso;

use Illuminate\Database\Eloquent\Model;

/** Una foto de una publicación. `ruta` es la del disco de uploads (privada: fotos-curso/). */
class FotoCurso extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'fotos_curso_fotos';

    protected $fillable = ['id_publicacion', 'ruta', 'orden'];

    public function publicacion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PublicacionFotosCurso::class, 'id_publicacion');
    }
}
