<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreParcialRequest extends FormRequest
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
            'numero' => 'required|integer|min:1',
            'modulo_id' => 'required|exists:modulos,id',
            'nota_maxima' => 'nullable|integer|min:1|max:100',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date'
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
            'numero.required' => 'El número de parcial es obligatorio',
            'numero.integer' => 'El número debe ser un entero',
            'numero.min' => 'El número debe ser al menos 1',
            'modulo_id.required' => 'El módulo es obligatorio',
            'modulo_id.exists' => 'El módulo seleccionado no existe',
            'nota_maxima.min' => 'La nota máxima debe ser al menos 1',
            'nota_maxima.max' => 'La nota máxima no puede exceder 100'
        ];
    }
}
