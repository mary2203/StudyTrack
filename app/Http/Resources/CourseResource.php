<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'semester_id' => $this->semester_id,
            'name' => $this->name,
            'code' => $this->code,
            'color_hex' => $this->color_hex,
            'target_grade' => $this->target_grade,
            'current_average' => $this->current_average,
            'is_classroom_synced' => $this->classroom_course_id !== null,
            
            'priority_rule' => $this->priorityRule ? [
                'id' => $this->priorityRule->id,
                'name' => $this->priorityRule->name,
                'description' => $this->priorityRule->description,
            ] : null,
        ];
    }
}
