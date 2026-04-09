<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreModuloRequest extends FormRequest
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
     * Prepare data for validation - Sanitización XSS
     * Se ejecuta antes de las reglas de validación
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('nombre')) {
            $this->merge([
                'nombre' => $this->sanitize($this->nombre),
            ]);
        }
        if ($this->has('descripcion')) {
            $this->merge([
                'descripcion' => $this->sanitize($this->descripcion),
            ]);
        }
    }

    /**
     * Sanitizar input contra XSS
     */
    private function sanitize($value): string
    {
        if ($value === null) {
            return '';
        }
        // Primero quitar tags HTML, luego escapar caracteres especiales
        return htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
            'materia_id' => 'required|exists:subjects,id'
        ];
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del módulo es requerido',
            'nombre.string' => 'El nombre debe ser texto',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres',
            'descripcion.string' => 'La descripción debe ser texto',
            'descripcion.max' => 'La descripción no puede exceder 1000 caracteres',
            'materia_id.required' => 'La materia es requerida',
            'materia_id.exists' => 'La materia seleccionada no existe'
        ];
    }
}
