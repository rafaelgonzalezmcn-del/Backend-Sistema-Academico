<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation()
    {
        // Convertir strings vacíos a null
        if (empty($this->role_id) && $this->role_id !== null) {
            $this->merge(['role_id' => null]);
        }
        if (empty($this->section_id) && $this->section_id !== null) {
            $this->merge(['section_id' => null]);
        }
        if (empty($this->identification_number) && $this->identification_number !== null) {
            $this->merge(['identification_number' => null]);
        }
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id ?? null;
        
        return [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users')->ignore($userId)
            ],
            'identification_number' => [
                'nullable',
                Rule::unique('users')->ignore($userId)
            ],
            'phone' => 'nullable|string|max:20',
            'role_id' => 'sometimes|exists:roles,id',
            'section_id' => 'nullable|exists:sections,id',
            'activo' => 'sometimes|boolean',
            'password' => 'sometimes|nullable|string|min:8'
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'El correo electrónico debe ser válido',
            'email.unique' => 'Ya existe un usuario con este correo',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres'
        ];
    }
}
