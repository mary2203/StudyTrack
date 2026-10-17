<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ActivityValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreActivityRequest extends FormRequest
{
    use ActivityValidation;

    public function authorize(): bool
    {
        return Gate::allows('create', \App\Models\Activity::class);
    }

    public function rules(): array
    {
        return $this->activityRules();
    }

    public function withValidator($validator): void
    {
        $validator->after(fn ($validator) => $this->verifyActivityRules($validator));
    }
}
