<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grade_id' => 'required|exists:grades,id',
            'school_year_id' => 'required|exists:school_years,id',
            'name' => 'required|string|max:255',
            'max_capacity' => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'grade_id.required' => 'El grado es requerido',
            'grade_id.exists' => 'El grado seleccionado no existe',
            'school_year_id.required' => 'El año lectivo es requerido',
            'school_year_id.exists' => 'El año lectivo seleccionado no existe',
            'name.required' => 'El nombre de la sección es requerido',
            'name.max' => 'El nombre no puede exceder 255 caracteres',
            'max_capacity.integer' => 'La capacidad máxima debe ser un número entero',
            'max_capacity.min' => 'La capacidad máxima no puede ser negativa',
        ];
    }
}
