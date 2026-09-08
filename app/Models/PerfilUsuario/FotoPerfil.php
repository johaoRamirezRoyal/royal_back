<?php

namespace App\Models\PerfilUsuario;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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

    // Se agrega a $appends para que salga siempre que el modelo (o una relación que lo
    // cargue, ej. Usuario::fotoPerfil) se serialice a array/JSON — antes esto solo se
    // calculaba a mano (ver PerfilUsuarioService::adjuntarUrlFoto, ya eliminado) en los
    // dos únicos métodos que lo llamaban, así que cualquier otro lugar que devolviera este
    // modelo (ej. GET /info-perfil, que carga usuario.fotoPerfil) nunca traía url_foto —
    // el frontend recibía la foto recién subida sin URL para mostrarla hasta que, por
    // casualidad, pasara por uno de esos dos métodos de nuevo.
    protected $appends = ['url_foto'];

    protected function urlFoto(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->nombre_foto
                ? Storage::disk(config('filesystems.uploads_disk', 'public'))->url($this->nombre_foto)
                : null,
        );
    }

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
