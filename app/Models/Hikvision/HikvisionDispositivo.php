<?php

namespace App\Models\Hikvision;

use Illuminate\Database\Eloquent\Model;

/**
 * Una terminal Hikvision del colegio (tabla del tenant): su conexión completa, con sus propias
 * credenciales. Reemplaza a HIKVISION_HOST/HOSTS/USERNAME/PASSWORD del .env.
 *
 * `device_id` ("host:puerto") es el id que usan el frontend, el caché de empleados y los logs:
 * se recalcula al guardar. `password` va cifrada con APP_KEY y nunca se serializa; si la fila
 * se cifró con otra APP_KEY (un dump de otro entorno), leerla lanza DecryptException.
 */
class HikvisionDispositivo extends Model
{
    public $timestamps = false;

    protected $table = 'hikvision_dispositivos';

    protected $fillable = [
        'nombre',
        'protocolo',
        'host',
        'puerto',
        'usuario',
        'password',
        'principal',
        'activo',
    ];

    protected $hidden = ['password'];

    protected $appends = ['tiene_password'];

    protected $casts = [
        'password' => 'encrypted',
        'puerto' => 'integer',
        'principal' => 'boolean',
        'activo' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(fn (self $d) => $d->device_id = "{$d->host}:{$d->puerto}");
    }

    public function getTienePasswordAttribute(): bool
    {
        return !empty($this->attributes['password']);
    }
}
