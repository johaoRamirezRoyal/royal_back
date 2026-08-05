<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ActualizarInventarioRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Actualización parcial de los datos básicos del ítem (no toca estado/área/
     * usuario: esos siguen gestionándose vía liberar/asignar/reportar/descontinuar).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            "descripcion" => ['sometimes', 'required', 'string', 'max:200'],

            "marca" => ['sometimes', 'nullable', 'string', 'max:200'],

            "modelo" => ['sometimes', 'nullable', 'string', 'max:200'],

            "precio" => ['sometimes', 'nullable', 'numeric', 'min:0'],

            "fecha_compra" => ['sometimes', 'nullable', 'date'],

            "codigo" => ['sometimes', 'nullable', 'string', 'max:200'],

            "detalles" => ['sometimes', 'nullable', 'string', 'max:300'],
        ];
    }

    public function messages(): array
    {
        return [
            'descripcion.required' => 'La descripción es obligatoria',
            'descripcion.string' => 'La descripción debe ser un texto',
            'descripcion.max' => 'La descripción no puede superar los 200 caracteres',

            'marca.string' => 'La marca debe ser un texto',
            'marca.max' => 'La marca no puede superar los 200 caracteres',

            'modelo.string' => 'El modelo debe ser un texto',
            'modelo.max' => 'El modelo no puede superar los 200 caracteres',

            'precio.numeric' => 'El precio debe ser un valor numérico',
            'precio.min' => 'El precio no puede ser negativo',

            'fecha_compra.date' => 'La fecha de compra debe ser una fecha válida',

            'codigo.string' => 'El código serial debe ser un texto',
            'codigo.max' => 'El código serial no puede superar los 200 caracteres',

            'detalles.string' => 'Los detalles deben ser un texto',
            'detalles.max' => 'Los detalles no pueden superar los 300 caracteres',
        ];
    }

    /**
     * Solo incluye las llaves que realmente vinieron en el request (soporta
     * actualización parcial: no pisa con null los campos que no se enviaron).
     *
     * @return array<string, mixed>
     */
    public function toInventarioUpdate(): array
    {
        return $this->only(['descripcion', 'marca', 'modelo', 'precio', 'fecha_compra', 'codigo', 'detalles']);
    }
}
