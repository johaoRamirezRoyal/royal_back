<?php
namespace App\Models\Usuarios;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NivelAcademico extends Model
{
    protected $table = 'nivel_academico';

    protected $primaryKey = 'id';

    public $timestamps = false;

    /** Método de calificación del nivel (ver NotasService). La planilla cualitativa aplica a CUALITATIVA y AMBAS. */
    public const CUALITATIVA = 'CUALITATIVA';
    public const CUANTITATIVA = 'CUANTITATIVA';
    public const AMBAS = 'AMBAS';
    public const METODOS = [self::CUALITATIVA, self::CUANTITATIVA, self::AMBAS];

    protected $fillable = [
        'id',
        'nombre',
        'metodo_calificacion',
        'tipo_unidad',
        'calificador',
    ];

    /** ¿Se califica con desempeños por indicadores (planilla cualitativa)? */
    public static function califCualitativa(?string $metodo): bool
    {
        return in_array($metodo ?? self::CUALITATIVA, [self::CUALITATIVA, self::AMBAS], true);
    }

    public function niveles(): HasMany
    {
        return $this->hasMany(Nivel::class, 'id_nivel_academico');
    }
}
