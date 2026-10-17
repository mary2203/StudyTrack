<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalendarEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'activity_id' => $this->activity_id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'description' => $this->description,
            'event_datetime' => $this->event_datetime?->toIso8601String(),
            'calculated_weight' => $this->calculated_weight,
            'priority_level_id' => $this->priority_level_id,
            'priority_rule' => $this->whenLoaded('priorityRule', fn () => $this->priorityRule ? [
                'id' => $this->priorityRule->id,
                'name' => $this->priorityRule->name,
                'badge_label' => $this->priorityRule->badge_label,
                'color_hex' => $this->priorityRule->color_hex,
            ] : null),
            'course' => $this->whenLoaded('course', fn () => $this->course ? [
                'id' => $this->course->id,
                'name' => $this->course->name,
                'code' => $this->course->code,
                'color_hex' => $this->course->color_hex,
            ] : null),
            'activity' => $this->whenLoaded('activity', fn () => $this->activity ? [
                'id' => $this->activity->id,
                'activity_type' => $this->activity->activity_type,
                'status' => $this->activity->status,
                'is_graded' => $this->activity->is_graded,
            ] : null),
        ];
    }
}
