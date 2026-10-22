<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/** @mixin \App\Models\SystemRecommendation */
class RecommendationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'title'         => $this->title,
            'message'       => $this->message,
            'urgency_level' => $this->urgency_level,
            'action_type'   => $this->action_type,
            'is_read'       => (bool) $this->is_read,
            'created_at'    => $this->created_at ? Carbon::parse($this->created_at)->toIso8601String() : null,
            'course'        => $this->whenLoaded('course', fn() => $this->course ? [
                'id'        => $this->course->id,
                'name'      => $this->course->name,
                'color_hex' => $this->course->color_hex,
            ] : null),
        ];
    }
}
