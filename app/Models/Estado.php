<?php
namespace App\Models;

use App\Models\Admisiones\Inscripcion;
use Illuminate\Database\Eloquent\Model;

class Estado extends Model
{
    protected $table = "admisiones_estados";

    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre',
        'user_log',
        'fechareg'
    ];

    protected $dates = ['fechareg'];

    public $timestamps = false;

    public function inscripcion(){
        return $this->hasMany(Inscripcion::class, 'id_estado', 'id');
    }
}