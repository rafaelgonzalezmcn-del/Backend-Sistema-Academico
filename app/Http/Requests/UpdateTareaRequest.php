<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTareaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare data for validation - Sanitización XSS
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('titulo')) {
            $this->merge([
                'titulo' => $this->sanitize($this->titulo),
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
        return htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // En update, el modulo_id es opcional (si no se envía, usar el actual de la tarea)
        $tarea = $this->route('tarea');
        $moduloIdActual = $tarea?->modulo_id;
        $moduloIdInput = $this->input('modulo_id');
        $moduloId = $moduloIdInput ?: $moduloIdActual;

        return [
            'titulo' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string|max:2000',
            'fecha_limite' => 'sometimes|date',
            'modulo_id' => 'nullable|integer|exists:modulos,id',
            'puntaje_maximo' => 'nullable|integer|min:1|max:1000',
            
            // Validación de consistencia: parcial debe pertenecer al módulo
            'parcial_id' => [
                'nullable',
                'integer',
                'exists:parciales,id',
                function ($attribute, $value, $fail) use ($moduloId) {
                    if ($value && $moduloId) {
                        $parcial = \App\Models\Parcial::find($value);
                        if ($parcial && $parcial->modulo_id != $moduloId) {
                            $fail('El parcial seleccionado no pertenece al módulo elegido.');
                        }
                    }
                },
            ],
            
            // Validación de consistencia: parametro debe pertenecer al parcial
            'parametro_id' => [
                'nullable',
                'integer',
                'exists:parametros,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $parcialId = $this->input('parcial_id');
                        if ($parcialId) {
                            $parametro = \App\Models\Parametro::find($value);
                            if ($parametro && $parametro->parcial_id != $parcialId) {
                                $fail('El parámetro seleccionado no pertenece al parcial elegido.');
                            }
                        } else {
                            $fail('Debe seleccionar un parcial antes de elegir un parámetro.');
                        }
                    }
                },
            ],
            
            'archivo' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,zip,ppt,pptx|max:10240'
        ];
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'titulo.required' => 'El título es obligatorio',
            'titulo.max' => 'El título no puede exceder 255 caracteres',
            'fecha_limite.required' => 'La fecha límite es obligatoria',
            'fecha_limite.date' => 'La fecha límite debe ser una fecha válida',
            'modulo_id.exists' => 'El módulo seleccionado no existe',
            'puntaje_maximo.min' => 'El puntaje máximo debe ser al menos 1',
            'puntaje_maximo.max' => 'El puntaje máximo no puede exceder 1000',
            'archivo.max' => 'El archivo no puede exceder 10MB',
            'archivo.mimes' => 'El archivo debe ser un documento PDF, Word, Excel, PowerPoint o ZIP'
        ];
    }
}
