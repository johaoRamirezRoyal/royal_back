<?php

namespace App\Models;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model 
{
    protected $fillable = [
        'user_id',
        'title',
        'message',
        'perfil',
        'nivel',
        'level',
        'type',
        'read_at',
    ];

    protected $cast = [
        'read_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user(){
        return $this->belongsTo(Usuario::class, 'user_id', 'id_user');
    }

    //Scope para notificaciones no leídas
    public function scopeUnread($query){
        return $query->whereNull('read_at');
    }

    public function scopeByLevel($query, $level){
        return $query->where('level', $level);
    }

    public function scopeByProfile($query, $perfil){
        return $query->where('perfil', $perfil);
    }
}