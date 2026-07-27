<?php
namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class Estado extends Model
{
    protected $table = "estado";

    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre',
        'user_log',
        'fechareg'
    ];

    protected $dates = ['fechareg'];

    public $timestamps = false;
}
