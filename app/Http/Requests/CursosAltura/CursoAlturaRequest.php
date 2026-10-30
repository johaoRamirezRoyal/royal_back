<?php

namespace App\Http\Requests\CursosAltura;

use App\Models\CursosAltura\CursoAltura;
use App\Models\Usuarios\Usuario;
use Illuminate\Foundation\Http\FormRequest;

class CursoAlturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Al crear el certificado es obligatorio; al editar, solo si se reemplaza.
        $esCreacion = $this->route('id') === null;

        return [
            'id_user' => ['required', 'integer', function (string $attribute, mixed $value, \Closure $fail) {
                if (! CursoAltura::filtrarOperativos(Usuario::query())->where('id_user', (int) $value)->exists()) {
                    $fail('El usuario seleccionado no es un operativo activo.');
                }
            }],
            'fecha_expedicion' => ['required', 'date', 'before_or_equal:today'],
            'fecha_finalizacion' => ['nullable', 'date', 'after_or_equal:fecha_expedicion'],
            'certificado' => [$esCreacion ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_user.required' => 'Selecciona el operativo',
            'fecha_expedicion.required' => 'La fecha de expedición es obligatoria',
            'fecha_expedicion.before_or_equal' => 'La fecha de expedición no puede ser futura',
            'fecha_finalizacion.after_or_equal' => 'La fecha de finalización no puede ser anterior a la de expedición',
            'certificado.required' => 'Adjunta el certificado del curso',
            'certificado.mimes' => 'El certificado debe ser PDF o imagen (JPG, PNG)',
            'certificado.max' => 'El certificado no puede superar los 10 MB',
        ];
    }
}
