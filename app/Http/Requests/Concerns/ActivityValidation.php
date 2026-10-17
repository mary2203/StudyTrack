<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

trait ActivityValidation
{
    protected function activityRules(bool $updating = false): array
    {
        $prefix = $updating ? 'sometimes' : 'required';

        return [
            'course_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('courses', 'id')->where(function (Builder $query) {
                    $query->whereNull('deleted_at')->whereIn(
                        'semester_id',
                        \Illuminate\Support\Facades\DB::table('academic_semesters')
                            ->select('id')->where('user_id', $this->user()->id)
                    );
                }),
            ],
            'title' => [$prefix, 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string'],
            'activity_type' => ['sometimes', Rule::in(['task', 'exam', 'project', 'reading', 'administrative', 'meeting'])],
            'is_graded' => ['sometimes', 'boolean'],
            'points_possible' => ['sometimes', 'numeric', 'gt:0', 'max:999.99'],
            'points_obtained' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999.99'],
            'due_datetime' => [$prefix, 'date'],
            'estimated_minutes' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::in(['pending', 'in_progress', 'completed', 'overdue'])],
            'priority_level_id' => ['sometimes', 'nullable', 'integer', Rule::exists('priority_rules', 'id')],
        ];
    }

    protected function verifyActivityRules(\Illuminate\Validation\Validator $validator, ?\App\Models\Activity $current = null): void
    {
        // En PATCH se combinan los cambios enviados con los valores actuales.
        $type = $this->input('activity_type', $current?->activity_type ?? 'task');
        $general = in_array($type, ['administrative', 'meeting'], true);
        $graded = $this->exists('is_graded')
            ? $this->boolean('is_graded')
            : ($this->exists('activity_type') && $general ? false : ($current?->is_graded ?? !$general));
        $courseId = $this->input('course_id', $current?->course_id);
        $possible = $this->input('points_possible', $current?->points_possible);
        $obtained = $this->input('points_obtained', $current?->points_obtained);

        if (!$general && ($courseId === null || $courseId === '')) {
            $validator->errors()->add('course_id', 'El curso es obligatorio para este tipo de actividad.');
        }

        if ($general && $graded) {
            $validator->errors()->add('is_graded', 'Los trámites y reuniones no se califican.');
        }

        if (!$graded) {
            if ($this->exists('points_possible')) {
                $validator->errors()->add('points_possible', 'Una actividad no calificable no acepta puntos posibles.');
            }
            if ($this->exists('points_obtained')) {
                $validator->errors()->add('points_obtained', 'Una actividad no calificable no acepta nota.');
            }
        } else {
            if ($possible === null || $possible === '' || !is_numeric($possible) || (float) $possible <= 0) {
                $validator->errors()->add('points_possible', 'Los puntos posibles deben ser mayores que cero.');
            } elseif ($obtained !== null && is_numeric($obtained) && (float) $obtained > (float) $possible) {
                $validator->errors()->add('points_obtained', 'La nota no puede superar los puntos posibles.');
            }
        }
    }
}
