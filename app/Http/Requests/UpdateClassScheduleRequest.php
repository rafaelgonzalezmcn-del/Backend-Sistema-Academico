<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClassScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validDays = config('schedules.valid_days', ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo']);
        
        return [
            'teacher_id' => 'sometimes|exists:users,id',
            'subject_id' => 'sometimes|exists:subjects,id',
            'section_id' => 'sometimes|exists:sections,id',
            'day' => ['sometimes', 'string', Rule::in($validDays)],
            'start_time' => 'sometimes',
            'end_time' => 'sometimes',
            // school_year_id fue eliminado - se deriva automáticamente desde section
        ];
    }

    public function messages(): array
    {
        return [
            'teacher_id.exists' => 'El profesor no existe',
            'subject_id.exists' => 'La materia no existe',
            'section_id.exists' => 'La sección no existe',
            'day.in' => 'El día no es válido',
        ];
    }
}
