<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255|unique:grades,name,' . $this->route('grade')->id,
            // El frontend envía 'order', la BD usa 'grade_order'
            'order' => 'sometimes|nullable|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'El nombre no puede exceder 255 caracteres',
            'name.unique' => 'Ya existe un grado con este nombre',
            'order.integer' => 'El orden debe ser un número entero',
            'order.min' => 'El orden debe ser mayor o igual a 1',
        ];
    }
}
