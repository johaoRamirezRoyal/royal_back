<?php

namespace App\Http\Requests\Cafeteria;

use App\Enums\Cafeteria\EstadoPuntoVenta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crear (POST puntos-venta/{puntoVenta}/terminales) o editar (PUT terminales/{terminal}) una
 * terminal. El código lo asigna el sistema (TER-001… dentro del punto).
 */
class TerminalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $dispositivo = trim((string) $this->input('identificador_dispositivo'));
        $this->merge(['identificador_dispositivo' => $dispositivo === '' ? null : $dispositivo]);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'identificador_dispositivo' => ['nullable', 'string', 'max:120', Rule::unique('caf_terminales_venta', 'identificador_dispositivo')->ignore($this->route('terminal'))],
            'estado' => ['sometimes', Rule::enum(EstadoPuntoVenta::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio',
            'identificador_dispositivo.unique' => 'Ese dispositivo ya está registrado en otra terminal',
            'estado.enum' => 'El estado no es válido',
        ];
    }
}
