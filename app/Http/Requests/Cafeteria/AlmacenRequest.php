<?php

namespace App\Http\Requests\Cafeteria;

use App\Enums\Cafeteria\EstadoAlmacen;
use App\Enums\Cafeteria\TipoAlmacen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crear o editar un almacén. `id_cafeteria` vacío = almacén central de la institución. El código
 * lo asigna el sistema (ALM-001…).
 */
class AlmacenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Solo si viene: al editar, no mandar `id_cafeteria` lo deja como está (no lo vuelve central).
        if ($this->has('id_cafeteria')) {
            $this->merge(['id_cafeteria' => $this->filled('id_cafeteria') ? $this->input('id_cafeteria') : null]);
        }
    }

    public function rules(): array
    {
        return [
            'id_cafeteria' => ['nullable', 'integer', Rule::exists('caf_cafeterias', 'id')],
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['required', Rule::enum(TipoAlmacen::class)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'estado' => ['sometimes', Rule::enum(EstadoAlmacen::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'id_cafeteria.exists' => 'La cafetería no existe',
            'nombre.required' => 'El nombre es obligatorio',
            'tipo.required' => 'Elige el tipo de almacén',
            'tipo.enum' => 'El tipo de almacén no es válido',
            'estado.enum' => 'El estado no es válido',
        ];
    }
}
