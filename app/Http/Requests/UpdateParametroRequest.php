<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateParametroRequest extends FormRequest
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
            'nombre' => [
                'nullable', 'string', 'max:255',
                Rule::unique('parametros', 'nombre')
                    ->where('parcial_id', $this->route('parametro')?->parcial_id)
                    ->ignore($this->route('parametro')?->id),
            ],
            'porcentaje' => 'nullable|numeric|min:0|max:100',
            'nota_maxima_default' => 'nullable|integer|min:1|max:1000',
            'activo' => 'nullable|boolean'
        ];
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'nombre.max' => 'El nombre no puede exceder 255 caracteres',
            'nombre.unique' => 'Ya existe un parámetro con ese nombre en este parcial',
            'porcentaje.min' => 'El porcentaje no puede ser menor a 0',
            'porcentaje.max' => 'El porcentaje no puede exceder 100',
            'nota_maxima_default.min' => 'La nota máxima debe ser al menos 1',
            'nota_maxima_default.max' => 'La nota máxima no puede exceder 1000'
        ];
    }
}
