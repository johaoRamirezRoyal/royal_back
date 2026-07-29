<?php

namespace App\Models\Hikvision;

use Illuminate\Database\Eloquent\Model;

class HikvisionDispositivo extends Model
{
    public $timestamps = false;

    protected $table = 'hikvision_dispositivos';

    protected $fillable = [
        'device_id',
        'nombre',
    ];
}
