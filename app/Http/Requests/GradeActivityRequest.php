<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class GradeActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('activity'));
    }

    public function rules(): array
    {
        return ['points_obtained' => ['required', 'numeric', 'min:0', 'max:999.99']];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $activity = $this->route('activity');

            if (!$activity->is_graded) {
                $validator->errors()->add('points_obtained', 'Esta actividad no admite calificación.');
            } elseif (is_numeric($this->input('points_obtained')) &&
                (float) $this->input('points_obtained') > (float) $activity->points_possible) {
                $validator->errors()->add('points_obtained', 'La nota no puede superar los puntos posibles.');
            }
        });
    }
}
