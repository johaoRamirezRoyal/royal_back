<?php

namespace App\Models\PerfilUsuario;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FotoPerfil extends Model
{
    protected $table = 'foto_perfil';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'nombre_foto',
        'id_user',
        'activo',
        'fechareg',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_user' => 'integer',
        'activo' => 'boolean',
        'fechareg' => 'datetime',
    ];

    /**
     * Usuario propietario de la foto.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_user', 'id');
    }

    /**
     * Scope para obtener únicamente las fotos activas.
     */
    public function scopeActivas($query)
    {
        return $query->where('activo', 1);
    }
}
