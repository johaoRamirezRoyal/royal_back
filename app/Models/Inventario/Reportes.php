<?php 

namespace App\Models\Inventario;

use App\Models\AnioEscolar\Anio;
use App\Models\AnioEscolar\Periodo;
use App\Models\Areas\Areas;
use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;

class Reportes extends Model
{
    protected $table = "reportes";

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_inventario',
        'observacion',
        'estado',
        'id_area',
        'id_user',
        'id_log',
        'id_resp',
        'id_reporte',
        'tipo_reporte',
        'visto_bueno',
        'fecha_respuesta',
        'fechareg',
        'descripcion',
        'evidencia',
        'id_anio',
        'periodo'
    ];

    protected $casts = [
        'fechareg' => 'datetime',
        'visto_bueno' => 'boolean',
    ];

    // Legacy: filas viejas guardan '0000-00-00 00:00:00' cuando aún no hay
    // respuesta; el cast 'datetime' lo parseaba como año "-0001" en vez de null.
    public function getFechaRespuestaAttribute(?string $value): ?\Carbon\Carbon
    {
        if (empty($value) || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        return \Carbon\Carbon::parse($value);
    }

    public function inventario(){
        return $this->belongsTo(Inventario::class, 'id_inventario');
    }

    public function usuario(){
        return $this->belongsTo(Usuario::class, 'id_user');
    }

    public function area(){
        return $this->belongsTo(Areas::class, 'id_area');
    }

    public function responsable(){
        return $this->belongsTo(Usuario::class, 'id_resp');
    }

    public function reportePadre(){
        return $this->belongsTo(Reportes::class, 'id_reporte');
    }

    public function respuestas(){
        return $this->hasMany(Reportes::class, 'id_reporte');
    }

    public function anio(){
        return $this->belongsTo(Anio::class, 'id_anio');
    }

    public function periodo(){
        return $this->belongsTo(Periodo::class, 'periodo');
    }

    public function solucion()
    {
        return $this->hasOne(Reportes::class, 'id_reporte', 'id');
    }
}