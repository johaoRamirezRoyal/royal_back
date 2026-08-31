<?php

namespace App\Http\Requests\Instituciones;

use App\Models\Instituciones\Institucion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstitucionAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isRequired = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'nombre' => [$isRequired, 'string', 'max:150'],
            // Qué formato de carta de recomendación usa esta institución al enviarla —
            // ver Institucion::TIPOS_DOCUMENTO / CartaRecomendacion.page.tsx.
            'tipo_documento' => ['sometimes', Rule::in(Institucion::TIPOS_DOCUMENTO)],
            // El NIT es opcional al editar — solo se re-hashea si se envía uno nuevo.
            'nit' => [$isRequired, 'string', 'max:50'],
            // Opcional en ambos casos — si se envía, el admin lo marca como verificado de
            // una (ver InstitucionAdminController), sin pasar por el OTP.
            'email' => ['nullable', 'email', 'max:140', Rule::unique('instituciones', 'email')->ignore($this->route('id'))],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.max' => 'El nombre no puede superar los 150 caracteres.',
            'tipo_documento.in' => 'El tipo de documento no es válido.',
            'nit.required' => 'El NIT es obligatorio.',
            'nit.max' => 'El NIT no puede superar los 50 caracteres.',
            'email.email' => 'El correo no tiene un formato válido.',
            'email.max' => 'El correo no puede superar los 140 caracteres.',
            'email.unique' => 'Este correo ya está registrado para otra institución.',
        ];
    }
}
