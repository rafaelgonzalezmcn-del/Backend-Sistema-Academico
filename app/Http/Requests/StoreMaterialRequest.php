<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMaterialRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * La autorización se maneja en el controlador mediante Policy
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'archivo' => 'required|file|mimes:pdf,zip,doc,docx|max:8192',
            'nombre_personalizado' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string|max:1000'
        ];
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'archivo.required' => 'El archivo es requerido',
            'archivo.file' => 'El archivo debe ser un archivo válido',
            'archivo.mimes' => 'El archivo debe ser de tipo: pdf, zip, doc, docx',
            'archivo.max' => 'El archivo no puede exceder 8MB',
            'nombre_personalizado.string' => 'El nombre debe ser texto',
            'nombre_personalizado.max' => 'El nombre no puede exceder 255 caracteres',
            'descripcion.string' => 'La descripción debe ser texto',
            'descripcion.max' => 'La descripción no puede exceder 1000 caracteres'
        ];
    }
}
