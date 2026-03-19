<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable implements JWTSubject
{
    // Nombre de la tabla
    protected $table = 'usuarios';

    // Clave primaria personalizada
    protected $primaryKey = 'id_user';

    // Si no usas created_at y updated_at estándar
    public $timestamps = false;

    protected $hidden = [
        'pass'
    ];

    // 🔹 JWT
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function getAuthPassword()
    {
        return $this->pass;
    }

    // Campos asignables masivamente
    protected $fillable = [
        'documento',
        'nombre',
        'apellido',
        'correo',
        'telefono',
        'asignatura',
        'user',
        'pass',
        'perfil',
        'id_empresa',
        'id_super_empresa',
        'user_log',
        'estado',
        'id_nivel',
        'id_curso',
        'id_grupo',
        'foto_carnet',
        'terminos',
        'curso_proximo_user',
        'fechareg',
        'fecha_activo',
        'fecha_inactivo',
        'fecha_editado',
        'firma_digital',
        'foto_digital'
    ];

    // Casts (opcional pero recomendado)
    protected $casts = [
        'perfil' => 'integer',
        'id_empresa' => 'integer',
        'id_super_empresa' => 'integer',
        'user_log' => 'integer',
        'id_nivel' => 'integer',
        'id_curso' => 'integer',
        'id_grupo' => 'integer',
        'terminos' => 'integer',
        'curso_proximo_user' => 'integer',
        'firma_digital' => 'integer',
        'fechareg' => 'datetime',
        'fecha_activo' => 'datetime',
        'fecha_inactivo' => 'datetime',
        'fecha_editado' => 'datetime'
    ];

    // Valores por defecto (opcional)
    protected $attributes = [
        'estado' => 'activo',
        'id_empresa' => 0,
        'id_super_empresa' => 0,
        'user_log' => 1,
        'id_nivel' => 0,
        'id_grupo' => 0,
        'terminos' => 0,
        'firma_digital' => 0
    ];
}
