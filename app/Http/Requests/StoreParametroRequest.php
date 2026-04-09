<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreParametroRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|in:actividades_clase,tareas,actuacion,examenes',
            'parcial_id' => 'required|exists:parciales,id',
            'porcentaje' => 'nullable|numeric|min:0|max:100',
            'nota_maxima_default' => 'nullable|integer|min:1|max:1000'
        ];
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres',
            'tipo.required' => 'El tipo es obligatorio',
            'tipo.in' => 'El tipo debe ser: actividades_clase, tareas, actuación o exámenes',
            'parcial_id.required' => 'El parcial es obligatorio',
            'parcial_id.exists' => 'El parcial seleccionado no existe',
            'porcentaje.min' => 'El porcentaje no puede ser menor a 0',
            'porcentaje.max' => 'El porcentaje no puede exceder 100',
            'nota_maxima_default.min' => 'La nota máxima debe ser al menos 1',
            'nota_maxima_default.max' => 'La nota máxima no puede exceder 1000'
        ];
    }
}
