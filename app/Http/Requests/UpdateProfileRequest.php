<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El controller ya verifica que es el usuario autenticado
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users')->ignore($userId)
            ],
            'phone' => 'nullable|string|max:20',
            'current_password' => 'nullable|string|min:8',
            'new_password' => 'nullable|string|min:8|confirmed'
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'El nombre es requerido',
            'email.required' => 'El correo electrónico es requerido',
            'email.email' => 'El correo electrónico debe ser válido',
            'email.unique' => 'Ya existe un usuario con este correo',
            'new_password.confirmed' => 'La confirmación de contraseña no coincide',
            'new_password.min' => 'La nueva contraseña debe tener al menos 8 caracteres',
            'current_password.min' => 'La contraseña actual debe tener al menos 8 caracteres'
        ];
    }

    /**
     * Determine if the user wants to change password
     */
    public function wantsToChangePassword(): bool
    {
        return $this->filled('current_password') || $this->filled('new_password');
    }
}
