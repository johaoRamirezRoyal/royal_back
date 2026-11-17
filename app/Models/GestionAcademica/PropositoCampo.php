<?php

namespace App\Models\GestionAcademica;

use Illuminate\Database\Eloquent\Model;

/** Campo de un propósito para la evaluación descriptiva (Fortalezas, Por fortalecer...). */
class PropositoCampo extends Model
{
    protected $table = 'academico_proposito_campo';

    protected $fillable = ['id_proposito', 'nombre', 'orden', 'activo'];

    protected $casts = ['activo' => 'boolean', 'orden' => 'integer', 'id_proposito' => 'integer'];
}
