<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'description' => $this->description,
            'activity_type' => $this->activity_type,
            'is_graded' => $this->is_graded,
            'points_possible' => $this->points_possible,
            'points_obtained' => $this->points_obtained,
            'is_classroom_synced' => $this->classroom_coursework_id !== null,
            'classroom_submission_state' => $this->classroom_submission_state,
            'due_datetime' => $this->due_datetime?->toIso8601String(),
            'estimated_minutes' => $this->estimated_minutes,
            'status' => $this->status,
            'priority_level_id' => $this->priority_level_id,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'course' => $this->whenLoaded('course', fn () => $this->course ? [
                'id' => $this->course->id,
                'name' => $this->course->name,
                'code' => $this->course->code,
                'color_hex' => $this->course->color_hex,
            ] : null),
            'priority_rule' => $this->whenLoaded('priorityRule', fn () => $this->priorityRule ? [
                'id' => $this->priorityRule->id,
                'name' => $this->priorityRule->name,
                'badge_label' => $this->priorityRule->badge_label,
                'color_hex' => $this->priorityRule->color_hex,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
