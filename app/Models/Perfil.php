<?php

namespace App\Models;

use App\Models\Opcion as ModelsOpcion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Opcion;

class Perfil extends Model
{
    use HasFactory;

    // Nombre de la tabla
    protected $table = 'perfiles';

    // Clave primaria
    protected $primaryKey = 'id_perfil';

    // Si no quieres que Eloquent gestione los timestamps created_at y updated_at
    public $timestamps = false;

    // Columnas que se pueden asignar masivamente
    protected $fillable = [
        'nombre',
        'user_log',
        'id_super_empresa',
        'estado',
        'fechareg',
    ];

    // Para que la columna fechareg sea tratada como instancia de Carbon
    protected $dates = ['fechareg'];

    public function opciones(){
        return $this->belongsToMany(
            ModelsOpcion::class,
            'cron_permisos',
            'id_perfil',
            'id_opcion'
        )->withPivot('activo', 'user_log', 'fechareg');
    }
}
