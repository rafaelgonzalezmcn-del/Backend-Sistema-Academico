<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEntregaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // La autorización se maneja en el controlador mediante Policy
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'archivo' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,zip,ppt,pptx,rar,jpg,jpeg,png|max:10240'
        ];
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'archivo.required' => 'Debes subir un archivo',
            'archivo.file' => 'El archivo debe ser un archivo válido',
            'archivo.mimes' => 'El archivo debe ser de tipo: pdf, doc, docx, xls, xlsx, zip, ppt, pptx, rar, jpg, jpeg, png',
            'archivo.max' => 'El archivo no puede exceder 10MB'
        ];
    }
}
