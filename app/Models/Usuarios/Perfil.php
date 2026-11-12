<?php

namespace App\Models\Usuarios;

use App\Enums\TipoPerfil;
use App\Models\PermisosAutorizaciones\Opcion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Perfil extends Model
{
    use HasFactory;

    // Nombre de la tabla
    protected $table = 'perfiles';

    // Clave primaria
    protected $primaryKey = 'id_perfil';

    // Si no quieres que Eloquent gestione los timestamps created_at y updated_at
    public $timestamps = false;

    // Columnas que se pueden asignar masivamente
    protected $fillable = [
        'nombre',
        'tipo', // App\Enums\TipoPerfil
        'user_log',
        'id_super_empresa',
        'estado',
        'fechareg',
    ];

    // Para que la columna fechareg sea tratada como instancia de Carbon
    protected $dates = ['fechareg'];

    /**
     * Ids de los perfiles de esos tipos en la base activa: whereIn('perfil', Perfil::idsDeTipo(...)).
     * Sin perfiles de ese tipo devuelve [] (un whereIn vacío no trae nada: falla cerrado).
     *
     * @return array<int>
     */
    public static function idsDeTipo(TipoPerfil ...$tipos): array
    {
        return static::query()
            ->whereIn('tipo', array_map(fn (TipoPerfil $t) => $t->value, $tipos))
            ->pluck('id_perfil')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Perfil con que se CREA un usuario de ese tipo (estudiante, proveedor...): el de menor id.
     * Falla si el colegio no tiene ninguno, en vez de crear el usuario con un perfil ajeno.
     */
    public static function idParaCrear(TipoPerfil $tipo): int
    {
        return (int) (static::query()->where('tipo', $tipo->value)->min('id_perfil')
            ?? throw new \RuntimeException("No hay un perfil de tipo {$tipo->value}: asígnalo en /permisos."));
    }

    public function opciones(){
        return $this->belongsToMany(
            Opcion::class,
            'cron_permisos',
            'id_perfil',
            'id_opcion'
        )->withPivot('activo', 'user_log', 'fechareg');
    }
}
