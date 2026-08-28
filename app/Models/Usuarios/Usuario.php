<?php

namespace App\Models\Usuarios;

use App\Models\Areas\Cursos;
use App\Models\Admisiones\Inscripcion;
use App\Models\GestionAcademica\DocenteAsignatura;
use App\Models\Inventario\Reportes;
use App\Models\Notificacion;
use App\Models\PerfilUsuario\FotoPerfil;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable implements JWTSubject
{
    // Se fija en el constructor (no como default estático) a `database.default` vigente en
    // ESE momento — así un usuario de un tenant distinto a `mysql` (ver login multi-tenant en
    // AuthServices::resolverUsuarioMultiTenant + JwtFromCookie, que switchean
    // `database.default` según el JWT) resuelve contra su propia base. Se sigue declarando
    // explícito (nunca null) por la misma razón que antes: un `belongsTo(Usuario::class)`
    // desde un modelo de otra connection (ej. LogActividad, en `admin_management`) no debe
    // heredar esa connection — Eloquent solo propaga la del padre cuando el relacionado no
    // declara la propia (ver HasRelationships::newRelatedInstance). `Usuario::on($conn)` sigue
    // pisando esto explícitamente para las búsquedas multi-tenant del login.
    protected $connection;

    public function __construct(array $attributes = [])
    {
        $this->connection = config('database.default', 'mysql');
        parent::__construct($attributes);
    }

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

    public function perfilRelacion(){
        return $this->belongsTo(Perfil::class, 'perfil', 'id_perfil');
    }

    public function nivelRelacion(){
        return $this->belongsTo(Nivel::class, 'id_nivel', 'id');
    }

    public function cursoRelacion(){
        return $this->belongsTo(Cursos::class, 'id_curso', 'id');
    }

    public function inscripciones(){
        return $this->hasMany(Inscripcion::class, 'id_usuario_registro', 'id_user');
    }

    public function reportes(){
        return $this->hasMany(Reportes::class, 'id_user');
    }

    public function setPassAttribute($value)
    {
        $this->attributes['pass'] = bcrypt($value);
    }

    public function notifications()
    {
        return $this->hasMany(Notificacion::class, 'user_id', 'id_user');
    }

    public function docente_asignaturas()
    {
        return $this->hasMany(DocenteAsignatura::class, 'id_docente', 'id_user');
    }

    public function fotoPerfil(){
        return $this->hasMany(FotoPerfil::class, 'id_user', 'id_user');
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
        'firma_digital'
    ];

    // Casts (opcional pero recomendado)
    protected $casts = [
        'perfil' => 'integer',
        'documento',
        'nombre',
        'apellido',
        'correo',
        'id_empresa' => 'integer',
        'id_super_empresa' => 'integer',
        'user_log' => 'integer',
        'id_nivel' => 'integer',
        'id_curso' => 'integer',
        'id_grupo' => 'integer',
        'terminos' => 'integer',
        'estado'   => 'string',
        'curso_proximo_user' => 'integer',
        'firma_digital' => 'integer',
        'asistenciaRegistrada' => 'boolean',
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
