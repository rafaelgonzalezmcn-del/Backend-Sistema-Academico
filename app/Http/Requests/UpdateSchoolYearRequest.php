<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolYearId = $this->route('schoolYear')?->id ?? null;

        return [
            'name' => 'sometimes|string|unique:school_years,name,' . $schoolYearId,
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'active' => 'boolean',
            'grade_order' => 'sometimes|nullable|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe un año lectivo con ese nombre',
            'start_date.date' => 'La fecha de inicio debe ser una fecha válida',
            'end_date.date' => 'La fecha de fin debe ser una fecha válida',
            'end_date.after' => 'La fecha de fin debe ser posterior a la fecha de inicio',
            'grade_order.integer' => 'El orden debe ser un número entero',
            'grade_order.min' => 'El orden debe ser mayor o igual a 1',
        ];
    }
}
