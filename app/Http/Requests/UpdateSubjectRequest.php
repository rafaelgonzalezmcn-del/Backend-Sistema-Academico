<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $subjectId = $this->route('subject')->id ?? null;
        
        return [
            'name' => 'sometimes|string|max:100|unique:subjects,name,' . $subjectId
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => 'El nombre debe ser texto',
            'name.max' => 'El nombre no puede exceder 100 caracteres',
            'name.unique' => 'Ya existe una materia con ese nombre'
        ];
    }
}
