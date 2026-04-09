<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Base validation — mode-specific checks done in controller
        return [
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'password' => 'nullable|min:8|string',
            'identification_number' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'student_id' => 'nullable|exists:users,id',
            'section_id' => 'required|exists:sections,id',
        ];
    }

    public function messages(): array
    {
        return [
            'section_id.required' => 'Debe seleccionar una sección',
            'section_id.exists' => 'La sección seleccionada no existe',
            'student_id.exists' => 'El estudiante seleccionado no existe',
        ];
    }

    /**
     * Whether this is a new student enrollment.
     */
    public function isNewStudent(): bool
    {
        return filled($this->input('first_name'));
    }
}
