<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarInventarioRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            "descripcion" => ['required', 'string', 'max:200'],

            "marca" => ['nullable', 'string', 'max:200'],

            "modelo" => ['nullable', 'string', 'max:200'],

            "precio" => ['nullable', 'numeric', 'min:0'],

            "estado" => [
                'integer',
                Rule::exists('estado', 'id'),
            ],

            "id_usuario" => [
                'required',
                'integer',
                Rule::exists('usuarios', 'id_user')->where('estado', 'activo'),
            ],

            "activo" => [
                'required',
                'in:0,1',
            ],

            "fecha_compra" => [
                'date',
                'nullable'
            ],

            "id_area" => [
                'required',
                'integer',
                Rule::exists("areas", "id")->where('activo', 1),
            ],

            "id_categoria" => [
                'required',
                'integer',
                Rule::exists('categoria', 'id')
            ],

            "id_compra" => [
                'nullable',
                'integer',
            ],

            "codigo" => ['nullable', 'string', 'max:200'],

            "detalles" => ['nullable', 'string', 'max:300'],

            "cantidad" => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            // descripcion
            'descripcion.required' => 'La descripción es obligatoria',
            'descripcion.string' => 'La descripción debe ser un texto',
            'descripcion.max' => 'La descripción no puede superar los 200 caracteres',

            // marca
            'marca.string' => 'La marca debe ser un texto',
            'marca.max' => 'La marca no puede superar los 200 caracteres',

            // modelo
            'modelo.string' => 'El modelo debe ser un texto',
            'modelo.max' => 'El modelo no puede superar los 200 caracteres',

            // precio
            'precio.numeric' => 'El precio debe ser un valor numérico',
            'precio.min' => 'El precio no puede ser negativo',

            // estado
            'estado.required' => 'El estado es obligatorio',
            'estado.integer' => 'El estado debe ser un número válido',
            'estado.exists' => 'El estado seleccionado no existe',

            // id_usuario
            'id_usuario.required' => 'El usuario es obligatorio',
            'id_usuario.integer' => 'El usuario debe ser un número válido',
            'id_usuario.exists' => 'El usuario no existe o no está activo',

            // activo
            'activo.required' => 'El campo activo es obligatorio',
            'activo.in' => 'El campo activo solo puede ser 0 (inactivo) o 1 (activo)',

            // fecha_compra
            'fecha_compra.date' => 'La fecha de compra debe ser una fecha válida',

            // id_area
            'id_area.required' => 'El área es obligatoria',
            'id_area.integer' => 'El área debe ser un número válido',
            'id_area.exists' => 'El área no existe o no está activa',

            // id_categoria
            'id_categoria.required' => 'La categoría es obligatoria',
            'id_categoria.integer' => 'La categoría debe ser un número válido',
            'id_categoria.exists' => 'La categoría seleccionada no existe',

            // id_compra
            'id_compra.integer' => 'El ID de compra debe ser un número válido',

            'codigo.string' => 'El código serial debe ser un texto',
            'codigo.max' => 'El código serial no puede superar los 200 caracteres',

            'detalles.string' => 'Los detalles deben ser un texto',
            'detalles.max' => 'Los detalles no pueden superar los 300 caracteres',

            'cantidad.integer' => 'La cantidad debe ser un número válido',
            'cantidad.min' => 'La cantidad debe ser al menos 1',
            'cantidad.max' => 'La cantidad no puede superar 500 por registro',
        ];
    }

    /**
     * Cuántas filas idénticas crear (una unidad física de inventario = una fila).
     */
    public function cantidad(): int
    {
        return (int) ($this->cantidad ?? 1);
    }

    /**
     * Summary of toInventarioCreate
     * @return array{activo: mixed, descripcion: mixed, estado: mixed, fecha_compra: mixed, id_area: mixed, id_categoria: mixed, id_compra: mixed, id_user: mixed, marca: mixed, modelo: mixed, precio: mixed}
     */
    public function toInventarioCreate(): array {
        return [
            'descripcion' => $this->descripcion,
            'marca' => $this->marca,
            'modelo' => $this->modelo,
            'precio' => $this->precio,
            'estado' => $this->estado,
            'id_user' => $this->id_usuario,
            'activo' => $this->activo,
            'fecha_compra' => $this->fecha_compra,
            'id_area' => $this->id_area,
            'id_categoria' => $this->id_categoria,
            'id_compra' => $this->id_compra,
            'codigo' => $this->codigo,
            'detalles' => $this->detalles,
        ];
    }
}
