<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:grades,name',
            // El frontend envía 'order', la BD usa 'grade_order'
            'order' => 'nullable|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del grado es requerido',
            'name.max' => 'El nombre no puede exceder 255 caracteres',
            'name.unique' => 'Ya existe un grado con este nombre',
            'order.integer' => 'El orden debe ser un número entero',
            'order.min' => 'El orden debe ser mayor o igual a 1',
        ];
    }
}
