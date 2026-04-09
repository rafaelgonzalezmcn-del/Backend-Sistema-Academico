<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalificarEntregaRequest extends FormRequest
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
     * Prepare data for validation - Sanitización XSS
     * Se ejecuta antes de las reglas de validación
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('observaciones')) {
            $this->merge([
                'observaciones' => $this->sanitize($this->observaciones),
            ]);
        }
    }

    /**
     * Sanitizar input contra XSS
     */
    private function sanitize($value): ?string
    {
        if ($value === null) {
            return null;
        }
        return htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'nota' => 'required|numeric|min:0|max:100',
            'observaciones' => 'nullable|string',
            'tarea_id' => 'nullable|exists:tareas,id',
            'estudiante_id' => 'nullable|exists:users,id'
        ];
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'nota.required' => 'La nota es requerida',
            'nota.numeric' => 'La nota debe ser un número',
            'nota.min' => 'La nota no puede ser menor a 0',
            'nota.max' => 'La nota no puede exceder el puntaje máximo',
            'observaciones.string' => 'Las observaciones deben ser texto',
            'tarea_id.exists' => 'La tarea no existe',
            'estudiante_id.exists' => 'El estudiante no existe'
        ];
    }
}
