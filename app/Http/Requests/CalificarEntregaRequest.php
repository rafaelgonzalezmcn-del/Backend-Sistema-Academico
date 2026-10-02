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
        // Solo se quitan etiquetas HTML. No se usa htmlspecialchars: guardaría
        // "&amp;" en la BD y Vue lo mostraría literal (Vue ya escapa al mostrar).
        return trim(strip_tags($value));
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // El máximo depende del puntaje de cada tarea (hasta 1000):
            // se valida en el controlador. Antes estaba fijo en 100.
            'nota' => 'required|numeric|min:0|max:1000',
            'observaciones' => 'nullable|string',
            // Obligatorios solo en POST /calificar-estudiante (calificación directa)
            'tarea_id' => [$this->esCalificacionDirecta() ? 'required' : 'nullable', 'integer', 'exists:tareas,id'],
            'estudiante_id' => [$this->esCalificacionDirecta() ? 'required' : 'nullable', 'integer', 'exists:users,id'],
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

    private function esCalificacionDirecta(): bool
    {
        return $this->is('api/calificar-estudiante');
    }
}
