<?php
namespace App\Models\Reservas;

use Illuminate\Database\Eloquent\Model;

class Horas extends Model
{
    protected $table = 'horas';

    protected $primaryKey = 'id';

    const CREATED_AT = "fechareg";

    protected $fillable = [
        'id', 
        'horas',
        'id_user',
        'activo',
        'fechareg',
    ];
}