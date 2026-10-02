<?php

namespace App\Http\Requests;

use App\Models\Parametro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreParametroRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    /**
     * El parcial viene en la URL (/parciales/{parcialId}/parametros);
     * no hace falta repetirlo en el cuerpo. Si no se indica tipo,
     * es un parámetro personalizado ("otro").
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'parcial_id' => $this->route('parcialId') ?? $this->input('parcial_id'),
            'tipo' => $this->input('tipo') ?: Parametro::TIPO_OTRO,
            'nombre' => is_string($this->input('nombre')) ? trim(strip_tags($this->input('nombre'))) : $this->input('nombre'),
        ]);
    }

    public function rules(): array
    {
        $parcialId = $this->input('parcial_id');

        return [
            // Dos parámetros del mismo parcial no pueden llamarse igual
            'nombre' => [
                'required', 'string', 'max:255',
                Rule::unique('parametros', 'nombre')->where('parcial_id', $parcialId),
            ],
            // Los tipos estándar solo pueden existir una vez por parcial;
            // "otro" (personalizado) puede repetirse
            'tipo' => array_filter([
                'required',
                Rule::in(array_merge(array_keys(Parametro::getTipos()), [Parametro::TIPO_OTRO])),
                $this->input('tipo') !== Parametro::TIPO_OTRO
                    ? Rule::unique('parametros', 'tipo')->where('parcial_id', $parcialId)
                    : null,
            ]),
            'parcial_id' => 'required|exists:parciales,id',
            'porcentaje' => 'nullable|numeric|min:0|max:100',
            'nota_maxima_default' => 'nullable|integer|min:1|max:1000'
        ];
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres',
            'tipo.required' => 'El tipo es obligatorio',
            'tipo.in' => 'El tipo debe ser: actividades_clase, tareas, actuacion, examenes u otro',
            'tipo.unique' => 'Este parcial ya tiene un parámetro de ese tipo',
            'nombre.unique' => 'Ya existe un parámetro con ese nombre en este parcial',
            'parcial_id.required' => 'El parcial es obligatorio',
            'parcial_id.exists' => 'El parcial seleccionado no existe',
            'porcentaje.min' => 'El porcentaje no puede ser menor a 0',
            'porcentaje.max' => 'El porcentaje no puede exceder 100',
            'nota_maxima_default.min' => 'La nota máxima debe ser al menos 1',
            'nota_maxima_default.max' => 'La nota máxima no puede exceder 1000'
        ];
    }
}
