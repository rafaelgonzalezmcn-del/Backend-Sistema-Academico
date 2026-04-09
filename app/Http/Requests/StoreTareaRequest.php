<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTareaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // F4-T4: Sanitización XSS se hace en el Service
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:2000',
            'fecha_limite' => 'required|date',
            'modulo_id' => 'required|integer|exists:modulos,id',
            'puntaje_maximo' => 'nullable|integer|min:1|max:1000',
            
            // Validación de consistencia: parcial debe pertenece al módulo
            'parcial_id' => [
                'nullable',
                'integer',
                'exists:parciales,id',
                function ($attribute, $value, $fail) {
                    // Usar request()->input() para obtener valor en tiempo de validación
                    $moduloId = request()->input('modulo_id');
                    if ($value && $moduloId) {
                        $parcial = \App\Models\Parcial::find($value);
                        if ($parcial && (int)$parcial->modulo_id !== (int)$moduloId) {
                            $fail('El parcial seleccionado no pertenece al módulo elegido.');
                        }
                    }
                },
            ],
            
            // Validación de consistencia: parametro debe pertenece al parcial
            'parametro_id' => [
                'nullable',
                'integer',
                'exists:parametros,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        // Usar request()->input() para tiempo de validación
                        $parcialId = request()->input('parcial_id');
                        if (!$parcialId) {
                            $fail('Debe seleccionar un parcial antes de elegir un parámetro.');
                        } else {
                            $parametro = \App\Models\Parametro::find($value);
                            if ($parametro && (int)$parametro->parcial_id !== (int)$parcialId) {
                                $fail('El parámetro seleccionado no pertenece al parcial elegido.');
                            }
                        }
                    }
                },
            ],
            
            'archivo' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,zip,ppt,pptx|max:10240'
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.required' => 'El título es obligatorio',
            'titulo.max' => 'El título no puede exceder 255 caracteres',
            'fecha_limite.required' => 'La fecha límite es obligatoria',
            'fecha_limite.date' => 'La fecha límite debe ser una fecha válida',
            'modulo_id.required' => 'El módulo es obligatorio',
            'modulo_id.exists' => 'El módulo seleccionado no existe',
            'puntaje_maximo.min' => 'El puntaje máximo debe ser al menos 1',
            'puntaje_maximo.max' => 'El puntaje máximo no puede exceder 1000',
            'archivo.max' => 'El archivo no puede exceder 10MB',
            'archivo.mimes' => 'El archivo debe ser un documento PDF, Word, Excel, PowerPoint o ZIP'
        ];
    }
}
