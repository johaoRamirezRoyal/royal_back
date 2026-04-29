<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionTutor extends Model
{
    protected $table = 'admission_tutors';

    protected $fillable = [
        'id',
        'email',
        'registered'
    ];

    const CREATE_AT = 'create_at';
    const UPDATE_AT = 'update_at';
}
