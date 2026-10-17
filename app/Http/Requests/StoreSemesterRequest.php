<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSemesterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Permitir que cualquier usuario autenticado cree un semestre
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */

    //reglas de validación para la creación de un semestre
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_current' => ['sometimes', 'boolean'],
        ];
    }

    //mensajes de error personalizados para la validación
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del semestre es obligatorio.',
            'name.string' => 'El nombre del semestre debe ser una cadena de texto.',
            'name.max' => 'El nombre del semestre no puede tener más de 255 caracteres.',
            'start_date.required' => 'La fecha de inicio es obligatoria.',
            'start_date.date' => 'La fecha de inicio debe ser una fecha válida.',
            'end_date.required' => 'La fecha de finalización es obligatoria.',
            'end_date.date' => 'La fecha de finalización debe ser una fecha válida.',
            'end_date.after' => 'La fecha de finalización debe ser posterior a la fecha de inicio.',
            'is_current.boolean' => 'El campo "is_current" debe ser verdadero o falso.',
        ];
    }
}
