<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|string',
            'identification_number' => 'nullable|unique:users,identification_number',
            'phone' => 'nullable|string|max:20',
            'role_id' => 'required|integer|exists:roles,id',
            'section_id' => 'nullable|integer|exists:sections,id',
            'activo' => 'boolean'
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'El nombre es requerido',
            'email.required' => 'El correo electrónico es requerido',
            'email.email' => 'El correo electrónico debe ser válido',
            'email.unique' => 'Ya existe un usuario con este correo',
            'password.required' => 'La contraseña es requerida',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'role_id.required' => 'Debe seleccionar un rol para el usuario',
            'role_id.exists' => 'El rol seleccionado no existe',
            'role_id.integer' => 'El rol seleccionado no es válido'
        ];
    }
}
