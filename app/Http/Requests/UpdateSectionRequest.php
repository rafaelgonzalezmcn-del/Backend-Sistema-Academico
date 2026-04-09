<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grade_id' => 'sometimes|exists:grades,id',
            'school_year_id' => 'sometimes|exists:school_years,id',
            'name' => 'sometimes|string|max:255',
            'max_capacity' => 'sometimes|nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'grade_id.exists' => 'El grado seleccionado no existe',
            'school_year_id.exists' => 'El año lectivo seleccionado no existe',
            'name.max' => 'El nombre no puede exceder 255 caracteres',
            'max_capacity.integer' => 'La capacidad máxima debe ser un número entero',
            'max_capacity.min' => 'La capacidad máxima no puede ser negativa',
        ];
    }
}
