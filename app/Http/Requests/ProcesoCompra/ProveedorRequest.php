<?php

namespace App\Http\Requests\ProcesoCompra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => $this->nombre ? trim($this->nombre) : null,
            'num_identificacion' => $this->num_identificacion ? trim($this->num_identificacion) : null,
            'correo' => $this->correo ? strtolower(trim($this->correo)) : null,
            'user' => $this->user
                ? strtolower(trim($this->user))
                : ($this->num_identificacion ? trim($this->num_identificacion) : null),
            'telefono' => $this->telefono ? trim($this->telefono) : null,
        ]);
    }

    public function rules(): array
    {
        $proveedorId = $this->route('id');
        $usuarioId = $proveedorId
            ? DB::table('proveedor_detalle')->where('id', $proveedorId)->value('id_proveedor')
            : null;

        return [
            'nombre' => ['required', 'string', 'max:200'],
            'identificacion' => ['nullable', 'string', 'max:200'],
            'num_identificacion' => ['required_without:user', 'nullable', 'string', 'max:200'],
            'direccion' => ['nullable', 'string', 'max:200'],
            'ciudad' => ['nullable', 'string', 'max:200'],
            'departamento' => ['nullable', 'string', 'max:200'],
            'pais' => ['nullable', 'string', 'max:200'],
            'telefono' => ['nullable', 'string', 'max:200'],
            'correo' => ['nullable', 'email', 'max:200', Rule::unique('usuarios', 'correo')->ignore($usuarioId, 'id_user')],
            'fecha_ingreso' => ['nullable', 'date'],
            'tipo' => ['nullable', 'string', 'max:200'],
            'tiempo_entrega' => ['nullable', 'string', 'max:200'],
            'garantia' => ['nullable', 'string', 'max:200'],
            'plazo_pago' => ['nullable', 'string', 'max:200'],
            'detalle_producto' => ['nullable', 'string', 'max:200'],
            'nom_representante' => ['nullable', 'string', 'max:200'],
            'identificacion_representante' => ['nullable', 'string', 'max:200'],
            'correo_representante' => ['nullable', 'email', 'max:200'],
            'telefono_representante' => ['nullable', 'string', 'max:200'],
            'regimen_proveedor' => ['nullable', 'string', 'max:200'],
            'contribuyente_proveedor' => ['nullable', 'string', 'max:200'],
            'autoretenedor_proveedor' => ['nullable', 'string', 'max:200'],
            'comercio_proveedor' => ['nullable', 'string', 'max:200'],
            'actividad_proveedor' => ['nullable', 'string', 'max:200'],
            'tarifa_proveedor' => ['nullable', 'string', 'max:200'],
            'comercial_nombre' => ['nullable', 'string', 'max:200'],
            'identificacion_comercial' => ['nullable', 'string', 'max:200'],
            'correo_comercial' => ['nullable', 'email', 'max:200'],
            'telefono_comercial' => ['nullable', 'string', 'max:200'],
            'direccion_comercial' => ['nullable', 'string', 'max:200'],
            'ciudad_comercial' => ['nullable', 'string', 'max:200'],
            'departamento_comercial' => ['nullable', 'string', 'max:200'],
            'user' => [
                $proveedorId ? 'nullable' : 'required_without:num_identificacion',
                'string',
                'max:200',
                Rule::unique('usuarios', 'user')->ignore($usuarioId, 'id_user'),
            ],
            'pass' => [$proveedorId ? 'nullable' : 'required', 'string', 'min:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del proveedor es obligatorio',
            'nombre.max' => 'El nombre no puede superar los 200 caracteres',
            'num_identificacion.required_without' => 'Debe indicar el número de identificación o el usuario de acceso',
            'correo.email' => 'El correo no es válido',
            'correo.unique' => 'El correo ya está registrado',
            'user.required_without' => 'Debe indicar el usuario de acceso o el número de identificación',
            'user.unique' => 'El usuario de acceso ya está registrado',
            'pass.required' => 'La contraseña es obligatoria',
            'pass.min' => 'La contraseña debe tener al menos 6 caracteres',
            'fecha_ingreso.date' => 'La fecha de ingreso no es válida',
        ];
    }

    public function toUsuarioData(): array
    {
        $data = [
            'documento' => $this->num_identificacion,
            'nombre' => $this->nombre,
            'apellido' => '',
            'user' => $this->user,
            'correo' => $this->correo,
            'telefono' => $this->telefono,
        ];

        if ($this->filled('pass')) {
            $data['pass'] = $this->pass;
        }

        return $data;
    }

    public function toProveedorData(): array
    {
        return [
            'nombre' => $this->nombre,
            'identificacion' => $this->identificacion,
            'num_identificacion' => $this->num_identificacion,
            'direccion' => $this->direccion,
            'ciudad' => $this->ciudad,
            'departamento' => $this->departamento,
            'pais' => $this->pais,
            'telefono' => $this->telefono,
            'correo' => $this->correo,
            'fecha_ingreso' => $this->fecha_ingreso,
            'tipo' => $this->tipo,
            'tiempo_entrega' => $this->tiempo_entrega,
            'garantia' => $this->garantia,
            'plazo_pago' => $this->plazo_pago,
            'detalle_producto' => $this->detalle_producto,
            'nom_representante' => $this->nom_representante,
            'identificacion_representante' => $this->identificacion_representante,
            'correo_representante' => $this->correo_representante,
            'telefono_representante' => $this->telefono_representante,
            'regimen_proveedor' => $this->regimen_proveedor,
            'contribuyente_proveedor' => $this->contribuyente_proveedor,
            'autoretenedor_proveedor' => $this->autoretenedor_proveedor,
            'comercio_proveedor' => $this->comercio_proveedor,
            'actividad_proveedor' => $this->actividad_proveedor,
            'tarifa_proveedor' => $this->tarifa_proveedor,
            'comercial_nombre' => $this->comercial_nombre,
            'identificacion_comercial' => $this->identificacion_comercial,
            'correo_comercial' => $this->correo_comercial,
            'telefono_comercial' => $this->telefono_comercial,
            'direccion_comercial' => $this->direccion_comercial,
            'ciudad_comercial' => $this->ciudad_comercial,
            'departamento_comercial' => $this->departamento_comercial,
        ];
    }
}