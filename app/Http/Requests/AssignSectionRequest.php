<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section_id' => 'nullable|exists:sections,id',
            'force' => 'nullable|boolean'
        ];
    }

    public function messages(): array
    {
        return [
            'section_id.exists' => 'La sección seleccionada no existe'
        ];
    }
}
