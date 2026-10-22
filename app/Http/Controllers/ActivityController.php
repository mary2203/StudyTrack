<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiResponses;
use App\Http\Requests\GradeActivityRequest;
use App\Http\Requests\StoreActivityRequest;
use App\Http\Requests\UpdateActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Activity::class);

        $filters = $request->validate([
            'course_id' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::in(['pending', 'in_progress', 'completed', 'overdue'])],
            'activity_type' => ['sometimes', Rule::in(['task', 'exam', 'project', 'reading', 'administrative', 'meeting'])],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $query = Activity::query()
            ->where('user_id', $request->user()->id)
            ->with(['course', 'priorityRule']);

        foreach (['course_id', 'status', 'activity_type'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (isset($filters['from'])) {
            $query->whereDate('due_datetime', '>=', $filters['from']);
        }
        if (isset($filters['to'])) {
            $query->whereDate('due_datetime', '<=', $filters['to']);
        }

        return ActivityResource::collection($query->orderBy('due_datetime')->paginate(15));
    }

    public function store(StoreActivityRequest $request)
    {
        $data = $request->validated();
        $type = $data['activity_type'] ?? 'task';
        $data['is_graded'] = $data['is_graded'] ?? !in_array($type, ['administrative', 'meeting'], true);
        $data['user_id'] = $request->user()->id;

        if (!$data['is_graded']) {
            $data['points_possible'] = 0;
            $data['points_obtained'] = null;
        }

        if (($data['status'] ?? '') === 'completed') {
            $data['completed_at'] = now();
        }

        $activity = Activity::create($data);

        return (new ActivityResource($activity->load(['course', 'priorityRule'])))
            ->response()->setStatusCode(201);
    }

    public function show(Activity $activity)
    {
        Gate::authorize('view', $activity);

        return new ActivityResource($activity->load(['course', 'priorityRule']));
    }

    public function update(UpdateActivityRequest $request, Activity $activity)
    {
        $data = $request->validated();

        if (array_key_exists('is_graded', $data)) {
            $data['is_graded'] = (bool) $data['is_graded'];
        }
        $type = $data['activity_type'] ?? $activity->activity_type;
        if (in_array($type, ['administrative', 'meeting'], true) &&
            !array_key_exists('is_graded', $data)) {
            $data['is_graded'] = false;
        }
        if (array_key_exists('is_graded', $data) && !$data['is_graded']) {
            $data['points_possible'] = 0;
            $data['points_obtained'] = null;
        }

        if (isset($data['status'])) {
            if ($data['status'] === 'completed' && $activity->status !== 'completed') {
                $data['completed_at'] = now();
            } elseif ($data['status'] !== 'completed') {
                $data['completed_at'] = null;
            }
        }

        $activity->update($data);

        return new ActivityResource($activity->refresh()->load(['course', 'priorityRule']));
    }

    public function destroy(Activity $activity)
    {
        Gate::authorize('delete', $activity);
        $activity->delete(); // SoftDelete; el Observer sincroniza el calendario.

        return $this->ok(null, 'Actividad eliminada.');
    }

    public function grade(GradeActivityRequest $request, Activity $activity)
    {
        Gate::authorize('update', $activity);
        $activity->update($request->validated());

        return new ActivityResource($activity->refresh()->load(['course', 'priorityRule']));
    }

    public function complete(Activity $activity)
    {
        Gate::authorize('update', $activity);
        $activity->update([
            'status' => 'completed',
            'completed_at' => $activity->completed_at ?? now(),
        ]);

        return new ActivityResource($activity->refresh()->load(['course', 'priorityRule']));
    }
}
