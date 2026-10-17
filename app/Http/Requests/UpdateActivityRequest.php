<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ActivityValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateActivityRequest extends FormRequest
{
    use ActivityValidation;

    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('activity'));
    }

    public function rules(): array
    {
        return $this->activityRules(true);
    }

    public function withValidator($validator): void
    {
        $validator->after(fn ($validator) => $this->verifyActivityRules($validator, $this->route('activity')));
    }
}
