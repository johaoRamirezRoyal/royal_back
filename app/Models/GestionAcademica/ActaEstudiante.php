<?php

namespace App\Models\GestionAcademica;

use App\Models\AnioEscolar\Anio;
use App\Models\AnioEscolar\PeriodoAcademico;
use App\Models\Areas\Cursos;
use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class ActaEstudiante extends Model
{
    protected $table = 'actas_estudiante';

    public const TIPO_DIALOGO_FORMATIVO = 'dialogo_formativo';

    public const TIPO_CONVIVENCIAL = 'convivencial';

    public const TIPOS = [self::TIPO_DIALOGO_FORMATIVO, self::TIPO_CONVIVENCIAL];

    /** Perfiles que pueden figurar como docente que solicita el acta (Docente, Docente ICT). */
    public const PERFILES_DOCENTE = [3, 14];

    /** Máximo de actas de diálogo formativo por estudiante y periodo (se avisa, no se bloquea). */
    public const MAX_DIALOGO_FORMATIVO = 3;

    protected $fillable = [
        'id_estudiante',
        'id_curso',
        'id_periodo_academico',
        'id_anio_escolar',
        'tipo',
        'asunto',
        'fecha',
        'observacion',
        'id_docente_solicita',
        'id_usuario_registra',
    ];

    public function docenteSolicita()
    {
        return $this->belongsTo(Usuario::class, 'id_docente_solicita', 'id_user');
    }

    public function estudiante()
    {
        return $this->belongsTo(Usuario::class, 'id_estudiante', 'id_user');
    }

    public function curso()
    {
        return $this->belongsTo(Cursos::class, 'id_curso');
    }

    public function periodo()
    {
        return $this->belongsTo(PeriodoAcademico::class, 'id_periodo_academico');
    }

    public function anio()
    {
        return $this->belongsTo(Anio::class, 'id_anio_escolar');
    }
}
