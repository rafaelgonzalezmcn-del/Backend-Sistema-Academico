<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClassScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validDays = config('schedules.valid_days', ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo']);
        
        return [
            'teacher_id' => 'required|exists:users,id',
            'subject_id' => 'required|exists:subjects,id',
            'section_id' => 'required|exists:sections,id',
            'day' => ['required', 'string', Rule::in($validDays)],
            'start_time' => 'required',
            'end_time' => 'required',
            // school_year_id fue eliminado - se deriva automáticamente desde section
        ];
    }

    public function messages(): array
    {
        return [
            'teacher_id.required' => 'El profesor es requerido',
            'teacher_id.exists' => 'El profesor no existe',
            'subject_id.required' => 'La materia es requerida',
            'subject_id.exists' => 'La materia no existe',
            'section_id.required' => 'La sección es requerida',
            'section_id.exists' => 'La sección no existe',
            'day.required' => 'El día es requerido',
            'day.in' => 'El día no es válido',
            'start_time.required' => 'La hora de inicio es requerida',
            'end_time.required' => 'La hora de fin es requerida',
        ];
    }
}
