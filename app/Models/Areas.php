<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Areas extends Model
{

    protected $table = "areas";

    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre',
        'user_log',
        'activo',
        'fechareg'
    ];

    protected $dates = ['fechareg'];

    public $timestamps = false;

    protected $attributes = [
        'activo' => 1
    ];
}