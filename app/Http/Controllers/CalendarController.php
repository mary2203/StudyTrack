<?php

namespace App\Http\Controllers;

use App\Http\Resources\CalendarEventResource;
use App\Models\CalendarEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'upcoming' => ['sometimes', 'boolean'],
        ]);

        $query = CalendarEvent::query()
            ->where('user_id', $request->user()->id)
            ->with(['priorityRule', 'course', 'activity']);

        if (isset($filters['from'])) {
            $query->where('event_datetime', '>=', Carbon::parse($filters['from'])->startOfDay());
        }
        if (isset($filters['to'])) {
            $query->where('event_datetime', '<=', Carbon::parse($filters['to'])->endOfDay());
        }
        if ($request->boolean('upcoming')) {
            $query->where('event_datetime', '>=', now());
        }

        return CalendarEventResource::collection($query->orderBy('event_datetime')->get());
    }
}
