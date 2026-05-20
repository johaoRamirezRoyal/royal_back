<?php

namespace App\Models\Biblioteca;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class PrestamosEjemplar extends Model
{
    protected $table = 'biblioteca_prestamos';

    public $timestamps = false;

    protected $fillable = [
        'id_ejemplar',
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

    public function ejemplar(){
        return $this->belongsTo(Ejemplares::class, 'id_ejemplar');
    }

    public function usuario(){
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }
}
