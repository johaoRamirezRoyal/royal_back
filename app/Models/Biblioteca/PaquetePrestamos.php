<?php

namespace App\Models\Biblioteca;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class PaquetePrestamos extends Model
{
    protected $table = 'biblioteca_paquete_prestamo';

    public $timestamps = false;

    protected $fillable = [
        'id_paquete',
        'id_usuario',
        'fecha_prestamo',
        'fecha_devolucion',
        'observacion',
        'id_log',
        'fechareg',
        'id_devuelto',
        'fecha_devuelto'
    ];

    protected $casts = [
        'fecha_prestamo' => 'date',
        'fecha_devolucion' => 'date',
        'fecha_devuelto' => 'datetime'
    ];

    public function paquete(){
        return $this->belongsTo(Paquetes::class, 'id_paquete');
    }

    public function usuario(){
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }
}
