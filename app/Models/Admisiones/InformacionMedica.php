<?php

namespace App\Models\Admisiones;

use App\Models\Admisiones\Aspirante;
use App\Models\Admisiones\Inscripcion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InformacionMedica extends Model
{
    use HasFactory;

    protected $table = 'admisiones_informacion_medica';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'aspirante_id',
        'id_inscripcion',
        'medico_nombre',
        'medico_telefono',
        'tiene_alergias',
        'detalle_alergias',
        'necesita_cuidados',
        'detalle_cuidados',
        'recibe_ayuda',
        'terapia_ocupacional',
        'terapia_lenguaje',
        'terapia_psicologica',
        'fonoaudiologia',
        'terapia_otros',
        'profesional_nombre',
        'profesional_telefono',
        'fecha_registro',
    ];

    protected $casts = [
        'tiene_alergias' => 'boolean',
        'necesita_cuidados' => 'boolean',
        'recibe_ayuda' => 'boolean',
        'terapia_ocupacional' => 'boolean',
        'terapia_lenguaje' => 'boolean',
        'terapia_psicologica' => 'boolean',
        'fonoaudiologia' => 'boolean',
        'terapia_otros' => 'boolean',
        'fecha_registro' => 'datetime',
    ];

    /**
     * Relación con el aspirante
     */
    public function aspirante()
    {
        return $this->belongsTo(
            Aspirante::class,
            'aspirante_id',
            'id'
        );
    }

    /**
     * Relación con la inscripción
     */
    public function inscripcion()
    {
        return $this->belongsTo(
            Inscripcion::class,
            'id_inscripcion',
            'id'
        );
    }
}
