<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCourseRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
            'semester_id' => ['required', 'integer', 'exists:academic_semesters,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255'],
            'color_hex' => ['required', 'string', 'max:7'],
            'target_grade' => ['required', 'numeric', 'between:0,100'],
        ];
    }

    public function messages(): array
    {
        return [
            'semester_id.required' => 'El campo semestre es obligatorio.',
            'semester_id.integer' => 'El campo semestre debe ser un número entero.',
            'semester_id.exists' => 'El semestre seleccionado no existe.'
            ,
            'name.required' => 'El campo nombre es obligatorio.',
            'name.string' => 'El campo nombre debe ser una cadena de texto.',
            'name.max' => 'El campo nombre no debe exceder los 255 caracteres.',

            'code.required' => 'El campo código es obligatorio.',
            'code.string' => 'El campo código debe ser una cadena de texto.',
            'code.max' => 'El campo código no debe exceder los 255 caracteres.',

            'color_hex.required' => 'El campo color hexadecimal es obligatorio.',
            'color_hex.string' => 'El campo color hexadecimal debe ser una cadena de texto.',
            'color_hex.max' => 'El campo color hexadecimal no debe exceder los 7 caracteres.',
            
            'target_grade.required' => 'El campo nota objetivo es obligatorio.',
            'target_grade.numeric' => 'El campo nota objetivo debe ser un número.',
            'target_grade.between' => 'El campo nota objetivo debe estar entre 0 y 100.',
        ];
    }
}
